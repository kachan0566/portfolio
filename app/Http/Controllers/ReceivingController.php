<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\ListSearch;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
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

        return view('receivings.index', [
            'receivings' => $receivings->concat($extra)->sortByDesc('date')->values(),
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

        if ($poType === PurchaseOrderType::YARN) {
            $qty = round((float) $request->input('qty'), 2);
            if ($qty <= 0 || $qty > $remaining + 0.001) {
                return redirect()->route('receivings.create', ['type' => $poType])
                    ->with('error', '入荷数量は 0.01〜'.number_format($remaining, 2).'kg の範囲で入力してください。');
            }
        } else {
            $qty = (int) $request->input('qty');
            if ($qty <= 0 || $qty > (int) floor($remaining)) {
                return redirect()->route('receivings.create', ['type' => $poType])
                    ->with('error', '入荷数量は 1〜'.(int) floor($remaining).'m の範囲で入力してください。');
            }
        }

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
            $receiving['qty_meters'] = $qty;
            $receiving['sku'] = $receiving['greige_sku'];
        } else {
            $receiving['product_id'] = (int) $po->product_id;
            $receiving['qty'] = $qty;
            $receiving['sku'] = $po->sku;
        }

        DemoState::applyReceiving($receiving);

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
            $message = "入荷 {$code} を登録し、染工場の生機在庫を {$qty}m 増加しました。";
        }

        return redirect()->route('receivings.index')->with('success', $message);
    }
}
