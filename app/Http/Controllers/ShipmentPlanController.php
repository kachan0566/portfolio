<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\ShipmentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentPlanController extends Controller
{
    public function create(int $order): View
    {
        $target = Order::findForDisplay($order) ?? abort(404);
        $remaining = Order::remainingFor($order);
        $existing = ShipmentPlan::forOrder($order);

        return view('shipment-plans.create', [
            'order' => $target,
            'remaining' => $remaining,
            'existing' => $existing,
        ]);
    }

    public function store(Request $request, int $order): RedirectResponse
    {
        $target = Order::findForDisplay($order) ?? abort(404);

        $request->validate([
            'confirmed_qty_m' => ['required', 'numeric', 'min:0.01'],
            'planned_ship_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $qty = (float) $request->input('confirmed_qty_m');
        $remaining = (float) Order::remainingFor($order);
        $alreadyPlanned = (float) ShipmentPlan::forOrder($order)
            ->whereIn('status', [ShipmentPlan::STATUS_CONFIRMED, ShipmentPlan::STATUS_PARTIAL])
            ->sum(fn ($p) => ShipmentPlan::unshippedQty($p));

        if ($qty > $remaining - $alreadyPlanned + 0.001) {
            return redirect()->route('orders.show', $order)
                ->with('error', '出荷確定数量が受注残を超えています。');
        }

        ShipmentPlan::create([
            'order_id' => $order,
            'product_id' => (int) $target->product_id,
            'planned_ship_date' => (string) $request->input('planned_ship_date'),
            'confirmed_qty_m' => $qty,
            'note' => (string) $request->input('note', ''),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('orders.show', $order)
            ->with('success', '出荷予定（出荷確定）を登録しました。');
    }
}
