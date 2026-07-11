<?php

namespace App\Http\Controllers;

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
                    $material = DemoData::findMaterial((int) ($r['material_id'] ?? 0));
                    $r['sku'] = $material?->sku ?? '—';
                    $r['unit'] = 'kg';
                    $r['qty'] = $r['qty_kg'] ?? $r['qty'] ?? 0;
                } elseif ($type === PurchaseOrderType::GREIGE) {
                    $r['sku'] = $r['greige_sku'] ?? '—';
                    $r['unit'] = 'm';
                    $r['qty'] = $r['qty_meters'] ?? $r['qty'] ?? 0;
                } else {
                    $product = DemoData::findProduct((int) ($r['product_id'] ?? 0));
                    $r['sku'] = $product?->sku ?? '—';
                    $r['unit'] = 'm';
                }

                return (object) array_merge($r, ['po_type' => $type]);
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
            ->filter(fn ($po) => DemoState::poRemaining((int) $po->id) > 0)
            ->values();

        return view('receivings.create', [
            'type' => $type,
            'pending' => $pending,
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
        $remaining = DemoState::poRemaining($poId);
        $rollLines = [];

        if ($poType === PurchaseOrderType::YARN) {
            $qty = round((float) $request->input('qty'), 2);
            if ($qty <= 0 || $qty > $remaining + 0.001) {
                return redirect()->route('receivings.create', ['type' => $poType])
                    ->with('error', '入荷数量は 0.01〜'.number_format($remaining, 2).'kg の範囲で入力してください。');
            }

            if (DemoData::usesReceivingDatabase() && DemoData::usesPurchaseOrderDatabase()) {
                $result = ReceivingRegistrar::register($poId, $date, $poType, qtyKg: $qty);

                return redirect()->route('receivings.index')->with('success', $result['message']);
            }
        } else {
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
                return redirect()->route('receivings.create', ['type' => $poType])
                    ->with('error', '入荷反数は 0.25反刻みで入力してください。');
            }

            if ($qty > (int) floor($remaining) + 1) {
                return redirect()->route('receivings.create', ['type' => $poType])
                    ->with('error', '入荷数量は発注残 '.(int) floor($remaining).'m 以内で入力してください。');
            }

            $rollLines = $this->normalizeRollLines($request->input('rolls', []), $qtyTan, $qty);
            if ($rollLines === []) {
                return redirect()->route('receivings.create', ['type' => $poType])
                    ->with('error', '反ごとの実測mを入力してください。');
            }

            if (DemoData::usesReceivingDatabase() && DemoData::usesPurchaseOrderDatabase()) {
                $result = ReceivingRegistrar::register(
                    $poId,
                    $date,
                    $poType,
                    qtyTan: $qtyTan,
                    qtyMeters: $qty,
                    rollLines: $rollLines,
                );

                return redirect()->route('receivings.index')->with('success', $result['message']);
            }
        }

        return $this->storeLegacy($request, $po, $poId, $poType, $date, $qty ?? 0, $qtyTan ?? 0, $rollLines);
    }

    /**
     * @param  list<array{tan_qty: float, actual_qty_m: float}>  $rollLines
     */
    private function storeLegacy(
        Request $request,
        object $po,
        int $poId,
        string $poType,
        string $date,
        float $qty,
        float $qtyTan,
        array $rollLines,
    ): RedirectResponse {
        $seq = DemoData::receivings()->count() + count(DemoState::extraReceivings()) + 1;
        $code = 'RC-'.date('ymd').'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        $receiving = [
            'code' => $code,
            'po_id' => $poId,
            'po_code' => $po->code,
            'po_type' => $poType,
            'supplier' => $po->supplier,
            'date' => $date,
        ];

        if ($poType === PurchaseOrderType::YARN) {
            $material = DemoData::findMaterial((int) $po->material_id);
            $receiving['material_id'] = (int) $po->material_id;
            $receiving['qty_kg'] = $qty;
            $receiving['sku'] = $material?->sku ?? '—';
        } elseif ($poType === PurchaseOrderType::GREIGE) {
            $receiving['greige_sku'] = $po->greige_sku ?? $po->sku;
            $receiving['qty_meters'] = (int) $qty;
            $receiving['qty_tan'] = $qtyTan;
            $receiving['sku'] = $receiving['greige_sku'];
        } else {
            $receiving['product_id'] = (int) $po->product_id;
            $receiving['qty'] = (int) $qty;
            $receiving['qty_tan'] = $qtyTan;
            $receiving['sku'] = $po->sku;
        }

        DemoState::applyReceiving($receiving);

        if ($poType === PurchaseOrderType::GREIGE) {
            TanRollRecorder::recordWeavingFromLines(
                $poId,
                (string) ($po->greige_sku ?? $po->sku),
                $rollLines,
                $date,
            );
        } elseif ($poType === PurchaseOrderType::PRODUCT) {
            TanRollRecorder::recordProductReceivingFromLines(
                $poId,
                (int) $po->product_id,
                $rollLines,
                $date,
            );
        }

        $message = "入荷 {$code} を登録しました。";

        if ($poType === PurchaseOrderType::PRODUCT) {
            $converted = StockAllocation::convertOnReceiving($poId, (int) $qty, $code);
            $message = "入荷 {$code} を登録し、製品在庫を {$qty}m 増加しました。";
            if (! empty($converted)) {
                $details = collect($converted)->map(function ($c) {
                    $order = DemoData::orders()->firstWhere('id', $c['order_id']);

                    return ($order?->code ?? '#'.$c['order_id'])." {$c['qty']}m";
                })->implode('、');
                $message .= " 発注引当から現在庫引当へ自動変換: {$details}";
            }
        } elseif ($poType === PurchaseOrderType::YARN) {
            $message = "入荷 {$code} を登録し、糸在庫を ".number_format($qty, 2)."kg 増加しました。";
        } elseif ($poType === PurchaseOrderType::GREIGE) {
            $message = "入荷 {$code} を登録し、染工場の生機在庫を {$qtyTan}反（実測 {$qty}m）増加しました。反明細に織り上がり実測を記録しました。";
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
}
