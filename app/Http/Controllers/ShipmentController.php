<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\ListSearch;
use App\Support\QtyHelper;
use App\Support\StockAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $shipments = ListSearch::filter(DemoData::shipments(), $search, [
            'code_fields' => ['code', 'order_code'],
        ]);

        return view('shipments.index', [
            'shipments' => $shipments,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $pending = DemoData::orders()
            ->whereIn('status', ['未出荷', '一部出荷'])
            ->map(function ($order) {
                $order->remaining = DemoState::orderRemaining($order->id);
                $order->stock_allocated = StockAllocation::stockAllocatedForOrder($order->id);
                $order->po_allocated = StockAllocation::poAllocatedForOrder($order->id);
                $order->shippable_qty = StockAllocation::shippableQty($order->id);
                $alloc = StockAllocation::statusForOrder($order);
                $order->allocation_status = $alloc['status'];
                $order->shippable_status = $alloc['shippable_status'];

                return $order;
            })
            ->filter(fn ($o) => $o->remaining > 0 && ($o->stock_allocated + $o->po_allocated) > 0)
            ->values();

        $selectedOrderId = (int) $request->query('order_id', 0);

        return view('shipments.create', compact('pending', 'selectedOrderId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $orderId = (int) $request->input('order_id');
        $qty = (int) $request->input('qty');

        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order) {
            return redirect()->route('shipments.create')
                ->with('error', '受注が見つかりません。');
        }

        $shippable = StockAllocation::shippableQty($orderId);
        if ($qty <= 0) {
            return redirect()->route('shipments.create', ['order_id' => $orderId])
                ->with('error', '出荷数量は 1 以上で入力してください。');
        }

        if ($qty > $shippable) {
            return redirect()->route('shipments.create', ['order_id' => $orderId])
                ->with('error', '出荷可能な現在庫引当は '.QtyHelper::format($shippable, $order->product_id).' です。発注引当のみの数量は出荷できません。');
        }

        $effectiveStock = DemoState::effectiveStock($order->product_id);
        if ($qty > $effectiveStock) {
            return redirect()->route('shipments.create', ['order_id' => $orderId])
                ->with('error', '現在庫（'.QtyHelper::format($effectiveStock, $order->product_id).'）を超える出荷はできません。');
        }

        DemoState::applyShipment($orderId, $order->product_id, $qty);

        return redirect()->route('shipments.index')
            ->with('success', '受注 '.$order->code.' から '.QtyHelper::format($qty, $order->product_id).' を出荷登録し、在庫を減少しました。');
    }
}
