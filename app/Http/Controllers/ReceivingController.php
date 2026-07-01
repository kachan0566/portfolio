<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\ListSearch;
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

        $extra = collect(DemoState::extraReceivings())->map(fn ($r) => (object) $r);

        return view('receivings.index', [
            'receivings' => $receivings->concat($extra)->sortByDesc('date')->values(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $pending = DemoData::purchaseOrders()
            ->filter(fn ($po) => DemoState::poRemaining($po->id) > 0)
            ->values();

        return view('receivings.create', compact('pending'));
    }

    public function store(Request $request): RedirectResponse
    {
        $poId = (int) $request->input('po_id');
        $qty = (int) $request->input('qty');
        $date = $request->input('date', date('Y-m-d'));

        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        if (! $po) {
            return redirect()->route('receivings.create')
                ->with('error', '発注が見つかりません。');
        }

        $remaining = DemoState::poRemaining($poId);
        if ($qty <= 0 || $qty > $remaining) {
            return redirect()->route('receivings.create')
                ->with('error', "入荷数量は 1〜{$remaining}m の範囲で入力してください。");
        }

        $code = 'RC-'.date('ymd').'-'.str_pad((string) (DemoData::receivings()->count() + count(DemoState::extraReceivings()) + 1), 3, '0', STR_PAD_LEFT);

        DemoState::applyReceiving([
            'code' => $code,
            'po_id' => $poId,
            'po_code' => $po->code,
            'product_id' => $po->product_id,
            'qty' => $qty,
            'date' => $date,
            'supplier' => $po->supplier,
        ]);

        $converted = StockAllocation::convertOnReceiving($poId, $qty, $code);

        $message = "入荷 {$code} を登録し、在庫を {$qty}m 増加しました。";
        if (! empty($converted)) {
            $details = collect($converted)->map(function ($c) {
                $order = DemoData::orders()->firstWhere('id', $c['order_id']);

                return ($order?->code ?? '#'.$c['order_id'])." {$c['qty']}m";
            })->implode('、');
            $message .= " 発注引当から現在庫引当へ自動変換: {$details}";
        }

        return redirect()->route('receivings.index')->with('success', $message);
    }
}
