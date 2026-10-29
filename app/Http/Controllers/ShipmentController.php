<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Inventory\ShipmentRollAllocator;
use App\Services\Shipment\ShipmentRegistrar;
use App\Support\Core\BusinessDate;
use App\Support\Core\DemoData;
use App\Support\Qty\FabricQuantity;
use App\Support\Master\ListSearch;
use App\Support\Inventory\ProductStock;
use App\Support\Qty\QtyHelper;
use App\Support\Inventory\StockAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * 出荷実績の一覧表示と受注に対する出荷登録を処理する。
 */
class ShipmentController extends Controller
{
    /**
     * 出荷一覧を検索・絞り込みして表示する。
     *
     * @param  Request  $request  一覧の検索クエリ
     * @return View  shipments.index
     */
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

    /**
     * 出荷可能な受注一覧と出荷登録フォームを表示する。
     *
     * @param  Request  $request  order_id で初期選択受注を指定可能
     * @return View  shipments.create
     */
    public function create(Request $request): View
    {
        $pending = DemoData::orders()
            ->whereIn('status', ['未出荷', '一部出荷'])
            ->map(function ($order) {
                $order->remaining = Order::remainingFor($order->id);
                $order->remaining_tan = Order::remainingTanFor($order->id);
                $order->stock_allocated = StockAllocation::stockAllocatedForOrder($order->id);
                $order->po_allocated = StockAllocation::poAllocatedForOrder($order->id);
                $order->shippable_qty = StockAllocation::shippableQty($order->id);
                $alloc = StockAllocation::statusForOrder($order);
                $order->allocation_status = $alloc['status'];
                $order->shippable_status = $alloc['shippable_status'];
                $order->fifo_preview = ShipmentRollAllocator::previewFifo(
                    (int) $order->product_id,
                    ($order->order_qty_mode ?? 'tan') === 'meters'
                        ? QtyHelper::tanCountCeilForShipment($order->remaining, (int) $order->product_id)
                        : $order->remaining_tan,
                );

                return $order;
            })
            ->filter(fn ($o) => $o->remaining > 0 && ($o->stock_allocated + $o->po_allocated) > 0)
            ->values();

        $selectedOrderId = (int) $request->query('order_id', 0);

        return view('shipments.create', compact('pending', 'selectedOrderId'));
    }

    /**
     * 出荷入力を検証し、ShipmentRegistrar で出荷実績を登録する。
     *
     * @param  Request  $request  order_id・出荷反数・分割出荷の実測 m
     * @return RedirectResponse  shipments.index または create（エラー時）
     */
    public function store(Request $request): RedirectResponse
    {
        $orderId = (int) $request->input('order_id');
        $order = DemoData::orders()->firstWhere('id', $orderId);
        if (! $order) {
            return redirect()->route('shipments.create')
                ->with('error', '受注が見つかりません。');
        }

        $isMetersOrder = ($order->order_qty_mode ?? 'tan') === 'meters';

        if ($isMetersOrder) {
            $qtyTan = QtyHelper::tanCountCeilForShipment(Order::remainingMetersFor($orderId), (int) $order->product_id);
            $qty = Order::remainingMetersFor($orderId);
        } else {
            $rawQtyTan = (float) $request->input('qty_tan');
            if (! QtyHelper::isValidTanStep($rawQtyTan)) {
                return redirect()->route('shipments.create', ['order_id' => $orderId])
                    ->with('error', '出荷反数は0.25反刻みで入力してください。');
            }

            $resolved = FabricQuantity::resolve(
                $rawQtyTan,
                null,
                (int) $order->product_id,
                false,
                null,
                FabricQuantity::CONTEXT_SHIPMENT,
            );
            $qtyTan = $resolved->qty_tan;
            $qty = QtyHelper::metersFromTan($qtyTan, (int) $order->product_id);
        }

        $shippable = StockAllocation::shippableQty($orderId);
        if ($qtyTan <= 0) {
            return redirect()->route('shipments.create', ['order_id' => $orderId])
                ->with('error', '出荷数量は0.25反以上で入力してください。');
        }

        if ($qty > $shippable && ! $isMetersOrder) {
            return redirect()->route('shipments.create', ['order_id' => $orderId])
                ->with('error', '出荷可能な現在庫引当は '.QtyHelper::format($shippable, $order->product_id).' です。発注引当のみの数量は出荷できません。');
        }

        $effectiveStock = ProductStock::effectiveStock($order->product_id);
        if ($isMetersOrder && $effectiveStock < Order::remainingMetersFor($orderId)) {
            return redirect()->route('shipments.create', ['order_id' => $orderId])
                ->with('error', '在庫が不足しています。FIFOで足りる反数を確保できません。');
        } elseif (! $isMetersOrder && $qty > $effectiveStock) {
            return redirect()->route('shipments.create', ['order_id' => $orderId])
                ->with('error', '現在庫（'.QtyHelper::format($effectiveStock, $order->product_id).'）を超える出荷はできません。');
        }

        $partialActualMeters = null;
        if (! $isMetersOrder) {
            $partialInput = $request->input('partial_actual_qty_m');
            if ($partialInput !== null && $partialInput !== '') {
                $partialActualMeters = (float) $partialInput;
                if ($partialActualMeters <= 0 || abs($partialActualMeters - round($partialActualMeters)) > 0.001) {
                    return redirect()->route('shipments.create', ['order_id' => $orderId])
                        ->with('error', '分割出荷の実測mは1m単位の正の整数で入力してください。');
                }
            }

            if (ShipmentRollAllocator::requiresPartialActualMeters((int) $order->product_id, $qtyTan)) {
                if ($partialActualMeters === null) {
                    return redirect()->route('shipments.create', ['order_id' => $orderId])
                        ->with('error', 'この出荷量では反を分割します。分割する反の出荷実測m（1m単位）を入力してください。');
                }
            }
        }

        if (Schema::hasTable('shipments')) {
            $result = ShipmentRegistrar::register(
                $orderId,
                $qtyTan,
                $isMetersOrder ? $qty : null,
                BusinessDate::today(),
                null,
                null,
                $partialActualMeters,
            );

            return redirect()->route('shipments.index')
                ->with('success', $result['message']);
        }

        return redirect()->route('shipments.create', ['order_id' => $orderId])
            ->with('error', '出荷登録にはデータベースのセットアップが必要です。');
    }
}
