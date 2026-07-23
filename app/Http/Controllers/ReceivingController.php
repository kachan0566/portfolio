<?php

namespace App\Http\Controllers;

use App\Support\MasterCatalog;

use App\Models\Material;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\Fabric\TanRollRecorder;
use App\Services\Receiving\ReceivingRegistrar;
use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\FabricQuantity;
use App\Support\ListSearch;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use App\Support\StockAllocation;
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

        if (! DemoData::usesReceivingDatabase()) {
            $extra = collect(DemoState::extraReceivings())->map(function ($r) {
                $r = (array) $r;
                $type = $r['po_type'] ?? PurchaseOrderType::PRODUCT;
                if ($type === PurchaseOrderType::YARN) {
                    $material = MasterCatalog::findMaterial((int) ($r['material_id'] ?? 0));
                    $r['sku'] = $material?->sku ?? '—';
                    $r['unit'] = 'kg';
                    $r['qty'] = $r['qty_kg'] ?? $r['qty'] ?? 0;
                } elseif ($type === PurchaseOrderType::GREIGE) {
                    $r['sku'] = $r['greige_sku'] ?? '—';
                    $r['unit'] = 'm';
                    $r['qty'] = $r['qty_meters'] ?? $r['qty'] ?? 0;
                } else {
                    $product = MasterCatalog::findProduct((int) ($r['product_id'] ?? 0));
                    $r['sku'] = $product?->sku ?? '—';
                    $r['unit'] = 'm';
                }

                return (object) array_merge($r, [
                    'po_type' => $type,
                    'line_no' => 1,
                    'line_count' => 1,
                ]);
            });
            $receivings = $receivings->concat($extra)->sortByDesc('date')->values();
        }

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
            ->filter(fn ($po) => DemoState::poRemaining($po->id) > 0)
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

        if (DemoData::usesReceivingDatabase() && DemoData::usesPurchaseOrderDatabase()) {
            $result = ReceivingRegistrar::register($poId, $date, $poType, $entries);

            return redirect()->route('receivings.index')->with('success', $result['message']);
        }

        return $this->storeLegacyFromEntries($request, $po, $poId, $poType, $date, $entries);
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
        $errors = [];

        foreach ($rawEntries as $index => $row) {
            if (! is_array($row) || empty($row['selected'])) {
                continue;
            }

            $poLineId = (int) ($row['po_line_id'] ?? 0);
            if ($poLineId <= 0) {
                continue;
            }

            $remaining = DemoState::poLineRemaining($poLineId);
            if ($remaining <= 0 && DemoData::usesPurchaseOrderDatabase()) {
                $errors[] = "明細行 {$index} は入荷残がありません。";

                continue;
            }

            if ($poType === PurchaseOrderType::YARN) {
                $qty = round((float) ($row['qty_kg'] ?? 0), 2);
                if ($qty <= 0 || $qty > $remaining + 0.001) {
                    $errors[] = "明細行 {$index} の入荷数量は 0.01〜".number_format($remaining, 2).'kg の範囲で入力してください。';

                    continue;
                }
                $entries[] = [
                    'purchase_order_line_id' => $poLineId,
                    'qty_kg' => $qty,
                ];
            } else {
                $poLine = PurchaseOrder::query()->with('greige', 'product')->find($poId)?->lines->firstWhere('id', $poLineId);
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
                    $errors[] = "明細行 {$index} の入荷反数は 0.25反刻みで入力してください。";

                    continue;
                }

                $lineRemaining = $remaining > 0 ? $remaining : DemoState::poRemaining($poId);
                if ($qty > (int) floor($lineRemaining) + 1) {
                    $errors[] = "明細行 {$index} の入荷数量は発注残 ".(int) floor($lineRemaining).'m 以内で入力してください。';

                    continue;
                }

                $rollLines = $this->normalizeRollLines($row['rolls'] ?? [], $qtyTan, $qty);
                if ($rollLines === []) {
                    $errors[] = "明細行 {$index} の反ごとの実測mを入力してください。";

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
        $remaining = DemoState::poRemaining($poId);
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

        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
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
        if (DemoData::usesPurchaseOrderDatabase()) {
            $line = PurchaseOrder::query()->with('lines')->find($poId)?->lines->sortBy('line_no')->first();

            return (int) ($line?->id ?? 0);
        }

        return 0;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function storeLegacyFromEntries(
        Request $request,
        object $po,
        int $poId,
        string $poType,
        string $date,
        array $entries,
    ): RedirectResponse {
        $seq = DemoData::receivings()->count() + count(DemoState::extraReceivings()) + 1;
        $code = 'RC-'.date('ymd').'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        $totalQty = 0;
        $totalTan = 0;
        $allRollLines = [];

        foreach ($entries as $entry) {
            if ($poType === PurchaseOrderType::YARN) {
                $totalQty += (float) ($entry['qty_kg'] ?? 0);
            } else {
                $totalQty += (int) ($entry['qty_meters'] ?? 0);
                $totalTan += (float) ($entry['qty_tan'] ?? 0);
                foreach ($entry['roll_lines'] ?? [] as $roll) {
                    $allRollLines[] = $roll;
                }
            }
        }

        $receiving = [
            'code' => $code,
            'po_id' => $poId,
            'po_code' => $po->code,
            'po_type' => $poType,
            'supplier' => $po->supplier,
            'date' => $date,
        ];

        if ($poType === PurchaseOrderType::YARN) {
            $material = MasterCatalog::findMaterial((int) $po->material_id);
            $receiving['material_id'] = (int) $po->material_id;
            $receiving['qty_kg'] = $totalQty;
            $receiving['sku'] = $material?->sku ?? '—';
        } elseif ($poType === PurchaseOrderType::GREIGE) {
            $receiving['greige_sku'] = $po->greige_sku ?? $po->sku;
            $receiving['qty_meters'] = (int) $totalQty;
            $receiving['qty_tan'] = $totalTan;
            $receiving['sku'] = $receiving['greige_sku'];
        } else {
            $receiving['product_id'] = (int) $po->product_id;
            $receiving['qty'] = (int) $totalQty;
            $receiving['qty_tan'] = $totalTan;
            $receiving['sku'] = $po->sku;
        }

        DemoState::applyReceiving($receiving);

        if ($poType === PurchaseOrderType::GREIGE) {
            TanRollRecorder::recordWeavingFromLines(
                $poId,
                (string) ($po->greige_sku ?? $po->sku),
                $allRollLines,
                $date,
            );
        } elseif ($poType === PurchaseOrderType::PRODUCT) {
            TanRollRecorder::recordProductReceivingFromLines(
                $poId,
                (int) $po->product_id,
                $allRollLines,
                $date,
            );
        }

        $lineCount = count($entries);
        $message = "入荷 {$code} を登録しました。（明細 {$lineCount} 行）";

        if ($poType === PurchaseOrderType::PRODUCT) {
            $converted = StockAllocation::convertOnReceiving($poId, (int) $totalQty, $code);
            $message = "入荷 {$code} を登録し、製品在庫を {$totalQty}m 増加しました。（明細 {$lineCount} 行）";
            if (! empty($converted)) {
                $details = collect($converted)->map(function ($c) {
                    $order = DemoData::orders()->firstWhere('id', $c['order_id']);

                    return ($order?->code ?? '#'.$c['order_id'])." {$c['qty']}m";
                })->implode('、');
                $message .= " 発注引当から現在庫引当へ自動変換: {$details}";
            }
        } elseif ($poType === PurchaseOrderType::YARN) {
            $message = "入荷 {$code} を登録し、糸在庫を ".number_format($totalQty, 2)."kg 増加しました。（明細 {$lineCount} 行）";
        } elseif ($poType === PurchaseOrderType::GREIGE) {
            $message = "入荷 {$code} を登録し、染工場の生機在庫を {$totalTan}反（実測 {$totalQty}m）増加しました。（明細 {$lineCount} 行）";
        }

        return redirect()->route('receivings.index')->with('success', $message);
    }

    /**
     * @param  mixed  $rollsInput
     * @return list<array{tan_qty: float, actual_qty_m: float}>
     */
    private function normalizeRollLines(mixed $rollsInput, float $headerTan, int $totalMeters): array
    {
        if (! is_array($rollsInput) || $rollsInput === []) {
            return TanRollRecorder::defaultRollLines($headerTan, $totalMeters);
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
        if (! DemoData::usesPurchaseOrderDatabase()) {
            return '{}';
        }

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
