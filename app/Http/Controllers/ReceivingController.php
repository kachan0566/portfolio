<?php

namespace App\Http\Controllers;

use App\Support\MasterCatalog;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Receiving\ReceivingRegistrar;
use App\Support\DemoData;
use App\Support\FabricQuantity;
use App\Support\ListSearch;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivingController extends Controller
{
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $receivings = ListSearch::filter(DemoData::receivings(), $search, [
            'code_fields' => ['code', 'po_code'],
            'date_field' => 'date',
        ]);

        return view('receivings.index', [
            'receivings' => $receivings,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $type = (string) $request->query('type', PurchaseOrderType::PRODUCT);
        if (! in_array($type, PurchaseOrderType::all(), true)) {
            $type = PurchaseOrderType::PRODUCT;
        }

        $pending = DemoData::purchaseOrders()
            ->filter(fn ($po) => ($po->type ?? '') === $type)
            ->filter(fn ($po) => PurchaseOrderStatus::isActive($po->status ?? ''))
            ->filter(fn ($po) => PurchaseOrder::remainingQtyFor((int) $po->id, $po) > 0)
            ->values();

        $poLinesJson = $this->pendingPoLinesJson($type, $pending);

        return view('receivings.create', [
            'type' => $type,
            'pending' => $pending,
            'poLinesJson' => $poLinesJson,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $poId = (int) $request->input('po_id');
        $date = (string) $request->input('date', date('Y-m-d'));

        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        if (! $po) {
            return redirect()->route('receivings.create', ['type' => $request->input('type')])
                ->with('error', '発注が見つかりません。');
        }

        $poType = (string) ($po->type ?? PurchaseOrderType::PRODUCT);
        $entries = $this->normalizeEntries($request, $poType, $poId);

        if ($entries === []) {
            return redirect()->route('receivings.create', ['type' => $poType])
                ->with('error', '入荷する明細行を1行以上選択し、数量を正しく入力してください。');
        }

        $result = ReceivingRegistrar::register($poId, $date, $poType, $entries);

        return redirect()->route('receivings.index')->with('success', $result['message']);
    }

    /**
     * @return list<array{
     *   purchase_order_line_id: int,
     *   qty_kg?: float,
     *   qty_tan?: float,
     *   qty_meters?: int,
     *   roll_lines?: list<array{tan_qty: float, actual_qty_m: float}>
     * }>
     */
    private function normalizeEntries(Request $request, string $poType, int $poId): array
    {
        $rawEntries = $request->input('entries');
        if (is_array($rawEntries) && $rawEntries !== []) {
            return $this->normalizeMultiLineEntries($request, $poType, $poId, $rawEntries);
        }

        return $this->normalizeLegacySingleEntry($request, $poType, $poId);
    }

    /**
     * @param  array<int, mixed>  $rawEntries
     * @return list<array<string, mixed>>
     */
    private function normalizeMultiLineEntries(Request $request, string $poType, int $poId, array $rawEntries): array
    {
        $entries = [];

        foreach ($rawEntries as $index => $row) {
            if (! is_array($row) || empty($row['selected'])) {
                continue;
            }

            $poLineId = (int) ($row['po_line_id'] ?? 0);
            if ($poLineId <= 0) {
                continue;
            }

            $remaining = PurchaseOrderLine::query()->find($poLineId)?->remainingQty() ?? 0.0;
            if ($remaining <= 0) {
                continue;
            }

            if ($poType === PurchaseOrderType::YARN) {
                $qty = round((float) ($row['qty_kg'] ?? 0), 2);
                if ($qty <= 0 || $qty > $remaining + 0.001) {
                    continue;
                }
                $entries[] = [
                    'purchase_order_line_id' => $poLineId,
                    'qty_kg' => $qty,
                ];
            } else {
                $poLine = PurchaseOrderLine::query()->with(['greige', 'product'])->find($poLineId);
                $productId = $poType === PurchaseOrderType::PRODUCT ? (int) ($poLine?->product_id ?? 0) : null;
                $greigeSku = $poType === PurchaseOrderType::GREIGE
                    ? (string) ($poLine?->greige?->sku ?? '')
                    : null;

                $resolved = FabricQuantity::resolve(
                    $row['qty_tan'] ?? null,
                    $row['qty_meters'] ?? null,
                    $productId,
                    $poType === PurchaseOrderType::GREIGE,
                    $greigeSku,
                    FabricQuantity::CONTEXT_RECEIVING,
                );
                $qty = $resolved->qty_meters;
                $qtyTan = $resolved->qty_tan;

                if ($qtyTan <= 0 || $qty <= 0 || ! QtyHelper::isValidReceivingTanStep($qtyTan)) {
                    continue;
                }

                $lineRemaining = $remaining > 0 ? $remaining : PurchaseOrder::remainingQtyFor($poId);
                if ($qty > (int) floor($lineRemaining) + 1) {
                    continue;
                }

                $rollLines = $this->normalizeRollLines($row['rolls'] ?? [], $qtyTan, $qty);
                if ($rollLines === []) {
                    continue;
                }

                $entries[] = [
                    'purchase_order_line_id' => $poLineId,
                    'qty_tan' => $qtyTan,
                    'qty_meters' => $qty,
                    'roll_lines' => $rollLines,
                ];
            }
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeLegacySingleEntry(Request $request, string $poType, int $poId): array
    {
        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        $remaining = PurchaseOrder::remainingQtyFor($poId, $po);
        $poLineId = $this->resolveLegacyPoLineId($poId);

        if ($poType === PurchaseOrderType::YARN) {
            $qty = round((float) $request->input('qty'), 2);
            if ($qty <= 0 || $qty > $remaining + 0.001) {
                return [];
            }

            return [[
                'purchase_order_line_id' => $poLineId,
                'qty_kg' => $qty,
            ]];
        }

        $productId = $poType === PurchaseOrderType::PRODUCT ? (int) $po->product_id : null;
        $greigeSku = $poType === PurchaseOrderType::GREIGE
            ? (string) ($po->greige_sku ?? $po->sku)
            : null;

        $resolved = FabricQuantity::resolve(
            $request->input('qty_tan'),
            $request->input('qty_meters', $request->input('qty')),
            $productId,
            $poType === PurchaseOrderType::GREIGE,
            $greigeSku,
            FabricQuantity::CONTEXT_RECEIVING,
        );
        $qty = $resolved->qty_meters;
        $qtyTan = $resolved->qty_tan;

        if ($qtyTan <= 0 || $qty <= 0 || ! QtyHelper::isValidReceivingTanStep($qtyTan)) {
            return [];
        }

        if ($qty > (int) floor($remaining) + 1) {
            return [];
        }

        $rollLines = $this->normalizeRollLines($request->input('rolls', []), $qtyTan, $qty);
        if ($rollLines === []) {
            return [];
        }

        return [[
            'purchase_order_line_id' => $poLineId,
            'qty_tan' => $qtyTan,
            'qty_meters' => $qty,
            'roll_lines' => $rollLines,
        ]];
    }

    private function resolveLegacyPoLineId(int $poId): int
    {
        $line = PurchaseOrder::query()->with('lines')->find($poId)?->lines->sortBy('line_no')->first();

        return (int) ($line?->id ?? 0);
    }

    /**
     * @param  mixed  $rollsInput
     * @return list<array{tan_qty: float, actual_qty_m: float}>
     */
    private function normalizeRollLines(mixed $rollsInput, float $headerTan, int $totalMeters): array
    {
        if (! is_array($rollsInput) || $rollsInput === []) {
            return \App\Services\Fabric\TanRollRecorder::defaultRollLines($headerTan, $totalMeters);
        }

        $lines = [];
        $tanSum = 0.0;

        foreach ($rollsInput as $row) {
            if (! is_array($row)) {
                continue;
            }
            $tanQty = QtyHelper::roundReceivingTan((float) ($row['tan_qty'] ?? 0));
            $actualM = round((float) ($row['actual_qty_m'] ?? 0), 2);
            if ($tanQty <= 0 || $actualM <= 0) {
                continue;
            }
            if (! QtyHelper::isValidReceivingTanStep($tanQty)) {
                continue;
            }
            $lines[] = ['tan_qty' => $tanQty, 'actual_qty_m' => $actualM];
            $tanSum += $tanQty;
        }

        if ($lines === []) {
            return [];
        }

        if (abs($tanSum - QtyHelper::roundReceivingTan($headerTan)) > 0.01) {
            return [];
        }

        return $lines;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $pending
     */
    private function pendingPoLinesJson(string $type, $pending): string
    {
        $map = [];
        foreach ($pending as $po) {
            $model = PurchaseOrder::query()
                ->with(['lines.material', 'lines.greige', 'lines.product'])
                ->find((int) $po->id);
            if ($model === null) {
                continue;
            }

            $lines = $model->lines
                ->sortBy('line_no')
                ->map(fn ($line) => $line->toReceivingMeta())
                ->filter(fn ($meta) => ($meta['remaining'] ?? 0) > 0)
                ->values()
                ->all();

            if ($lines !== []) {
                $map[(int) $po->id] = $lines;
            }
        }

        return json_encode($map, JSON_UNESCAPED_UNICODE);
    }
}
