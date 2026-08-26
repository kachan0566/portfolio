<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\AllocationConversion;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Support\DemoData;
use App\Support\MasterCatalog;
use App\Support\ProductStock;
use App\Support\FabricQuantity;
use App\Support\ListSearch;
use App\Support\QtyHelper;
use App\Support\OrderProductionStatus;
use App\Support\ShipmentPlan;
use App\Support\StockAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $orders = ListSearch::filter(Order::displayList(), $search, [
            'status_resolver' => function ($order, $status) {
                if ($status === '出荷残あり') {
                    return $order->shipped < $order->qty;
                }

                return $order->status === $status;
            },
        ])->sortBy([
            ['order_date', 'desc'],
            ['id', 'desc'],
        ])->map(function ($order) {
            return $this->enrichWithAllocation($order, withPrice: true);
        });

        return view('orders.index', [
            'orders' => $orders,
            'search' => $search,
        ]);
    }

    public function show(int $order): View
    {
        $target = $this->orderOrFail($order);
        $target = $this->enrichWithAllocation($target);

        $product = MasterCatalog::findProductOrFail($target->product_id);
        $effectiveStock = ProductStock::effectiveStock($target->product_id);

        $shipRate = $target->qty > 0
            ? (int) round(Order::shippedMetersFor($target->id) / $target->qty * 100)
            : 0;

        $allocationRate = $target->remaining > 0
            ? (int) round($target->allocated / $target->remaining * 100)
            : 0;

        $confirmedQty = Order::shippedMetersFor($target->id) + $target->allocated;
        $confirmedRate = $target->qty > 0
            ? (int) round($confirmedQty / $target->qty * 100)
            : 0;

        $stockRate = $target->qty > 0
            ? (int) round($effectiveStock / $target->qty * 100)
            : 0;

        $shipments = DemoData::shipments()
            ->where('order_code', $target->code)
            ->sortBy('date')
            ->values();

        $productPurchaseOrders = DemoData::purchaseOrders()
            ->where('product_id', $target->product_id)
            ->values();
        $purchaseOrdersById = $productPurchaseOrders->keyBy('id');
        $linkedPurchaseOrders = $productPurchaseOrders
            ->where('order_id', $target->id)
            ->values();
        $freePurchaseOrders = $productPurchaseOrders
            ->where('order_id', null)
            ->values();
        $siblingOrders = Order::query()
            ->with(['customer', 'product'])
            ->where('product_id', $target->product_id)
            ->where('id', '!=', $target->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Order $row) => $row->toDisplayObject());

        $allocationLines = StockAllocation::linesForOrder($target->id);
        $stockAllocationLines = StockAllocation::stockLinesForOrder($target->id);
        $poAllocationLines = StockAllocation::poLinesForOrder($target->id);
        $conversionHistory = AllocationConversion::eventsForOrder($target->id);
        $shippableQty = StockAllocation::shippableQty($target->id);

        $unallocated = max(0, $target->remaining - $target->allocated);
        $stockShortage = max(0, $unallocated - $target->stock_allocated);

        $stockCheckLevel = match (true) {
            $target->remaining === 0 => null,
            $unallocated === 0 => 'allocated',
            $target->stock_allocated >= $unallocated => 'full',
            $target->stock_allocated > 0 => 'partial',
            default => 'none',
        };

        $sameProductOrders = Order::query()
            ->with(['customer', 'product'])
            ->where('product_id', $target->product_id)
            ->orderBy('due_date')
            ->get()
            ->map(function (Order $row) {
                $order = $this->enrichWithAllocation($row->toDisplayObject());
                $order->allocation_lines = StockAllocation::linesForOrder($order->id);
                $order->stock_lines = StockAllocation::stockLinesForOrder($order->id);
                $order->po_lines = StockAllocation::poLinesForOrder($order->id);

                return $order;
            })
            ->filter(fn ($o) => $o->remaining > 0)
            ->values();

        $poOptions = StockAllocation::poOptionsFromPurchases($productPurchaseOrders, $target->product_id);

        $otherOrdersStockAllocated = $sameProductOrders
            ->where('id', '!=', $target->id)
            ->sum('stock_allocated');

        $availableStockForThis = max(0, $effectiveStock - $otherOrdersStockAllocated);
        $unallocatedStock = StockAllocation::unallocatedStockForProduct($target->product_id);
        $unallocatedPoRemaining = StockAllocation::unallocatedPoRemainingForProduct($target->product_id);
        $supplyShortage = StockAllocation::supplyShortageForOrder($target->id);

        return view('orders.show', [
            'order' => $target,
            'product' => $product,
            'effectiveStock' => $effectiveStock,
            'shipRate' => $shipRate,
            'allocationRate' => $allocationRate,
            'confirmedQty' => $confirmedQty,
            'confirmedRate' => $confirmedRate,
            'unallocated' => $unallocated,
            'stockCoversRemaining' => $target->stock_allocated >= $target->remaining,
            'stockShortage' => $stockShortage,
            'stockCheckLevel' => $stockCheckLevel,
            'shipments' => $shipments,
            'orderAmount' => $product->price * $target->qty,
            'stockRate' => $stockRate,
            'linkedPurchaseOrders' => $linkedPurchaseOrders,
            'freePurchaseOrders' => $freePurchaseOrders,
            'productionStatus' => OrderProductionStatus::rowsForOrder($target),
            'allocationLines' => $allocationLines,
            'stockAllocationLines' => $stockAllocationLines,
            'poAllocationLines' => $poAllocationLines,
            'conversionHistory' => $conversionHistory,
            'shippableQty' => $shippableQty,
            'sameProductOrders' => $sameProductOrders,
            'otherOrdersStockAllocated' => $otherOrdersStockAllocated,
            'availableStockForThis' => $availableStockForThis,
            'unallocatedStock' => $unallocatedStock,
            'unallocatedPoRemaining' => $unallocatedPoRemaining,
            'supplyShortage' => $supplyShortage,
            'stockPoOptions' => $poOptions['stock'],
            'poPoOptions' => $poOptions['po'],
            'purchaseOrdersById' => $purchaseOrdersById,
            'shipmentPlans' => ShipmentPlan::forOrder($target->id),
            'siblingOrders' => $siblingOrders,
        ]);
    }

    public function create(): View
    {
        return view('orders.create', [
            'customers' => Customer::query()->orderBy('id')->get(),
            'products' => MasterCatalog::products(),
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $attributes = $this->attributesFromRequest($request);
        $attributes['code'] = $this->generateOrderCode((string) $request->input('order_date'));
        $attributes['planned_ship_date'] = $request->input('due_date');
        $attributes['shipped_qty_tan'] = 0;
        $attributes['shipped_qty_m'] = 0;

        $order = Order::query()->create($attributes);

        return redirect()->route('orders.show', $order->id)
            ->with('just_created', true)
            ->with('success', '受注を登録しました。在庫状況を確認してください。');
    }

    public function edit(int $order): View
    {
        $target = $this->orderOrFail($order);

        return view('orders.edit', [
            'order' => $target,
            'customers' => Customer::query()->orderBy('id')->get(),
            'products' => MasterCatalog::products(),
        ]);
    }

    public function update(StoreOrderRequest $request, int $order): RedirectResponse
    {
        Order::query()->findOrFail($order)->update($this->attributesFromRequest($request));

        return redirect()->route('orders.show', $order)
            ->with('success', '受注を更新しました。');
    }

    public function destroy(int $order): RedirectResponse
    {
        return redirect()->route('orders.index')
            ->with('success', '受注を削除しました。（テストデータのため保存はされません）');
    }

    public function linkPurchase(int $order, int $purchase): RedirectResponse
    {
        $target = $this->orderOrFail($order);
        $poModel = PurchaseOrder::query()->with('lines')->find($purchase) ?? abort(404);
        $productId = $poModel->productIdForLink();

        if ($productId === null || $productId !== (int) $target->product_id) {
            return redirect()->route('orders.show', $order)
                ->with('error', '品番が異なる発注は紐づけできません。');
        }

        if ($poModel->order_id !== null) {
            return redirect()->route('orders.show', $order)
                ->with('error', 'この発注はすでに別の受注に紐づいています。');
        }

        PurchaseOrder::linkToOrder($purchase, $order);

        $remaining = Order::remainingFor($order);
        $message = "発注 {$poModel->code} を受注 {$target->code} に紐づけました。";

        if ($remaining > 0) {
            $effectiveStock = ProductStock::effectiveStock($target->product_id);
            $stockAlloc = StockAllocation::stockAllocatedForOrder($order);
            $poAlloc = StockAllocation::poAllocatedForOrder($order);
            $need = max(0, $remaining - $stockAlloc - $poAlloc);

            if ($need > 0 && PurchaseOrder::hasReceivedFor($purchase)) {
                $received = PurchaseOrder::receivedQtyFor($purchase);
                $usage = StockAllocation::usageByPoAndType($target->product_id);
                $stockUsedFromPo = $usage['stock'][$purchase] ?? 0;
                $availableFromPo = max(0, $received - $stockUsedFromPo);
                $autoStock = min($need, $availableFromPo, max(0, $effectiveStock - StockAllocation::stockUsageForProduct($target->product_id)));

                if ($autoStock > 0) {
                    StockAllocation::addLine(
                        $target->product_id,
                        $order,
                        $purchase,
                        QtyHelper::tanCount($autoStock, $target->product_id),
                        StockAllocation::TYPE_STOCK
                    );
                    $message .= " 現在庫引当 {$autoStock}m を登録しました。";
                    $need -= $autoStock;
                }
            }

            if ($need > 0 && PurchaseOrder::hasRemainingFor($purchase)) {
                $poRemaining = PurchaseOrder::remainingQtyFor($purchase);
                $usage = StockAllocation::usageByPoAndType($target->product_id);
                $poUsed = $usage['po'][$purchase] ?? 0;
                $availablePo = max(0, $poRemaining - $poUsed);
                $autoPo = min($need, $availablePo);

                if ($autoPo > 0) {
                    StockAllocation::addLine(
                        $target->product_id,
                        $order,
                        $purchase,
                        QtyHelper::tanCount($autoPo, $target->product_id),
                        StockAllocation::TYPE_PO
                    );
                    $message .= " 発注引当 {$autoPo}m を登録しました。";
                }
            }
        }

        return redirect()->route('orders.show', $order)->with('success', $message);
    }

    public function clearAllocation(int $order): RedirectResponse
    {
        $target = $this->orderOrFail($order);

        if (StockAllocation::get($order) === 0) {
            return redirect()->route('orders.show', $order)
                ->with('error', '解除する引当がありません。');
        }

        StockAllocation::clearForOrder($order);

        return redirect()->route('orders.show', $order)
            ->with('success', "受注 {$target->code} の引当をすべて解除しました。（発注の紐づけはそのままです）");
    }

    public function saveAllocation(Request $request, int $order): RedirectResponse
    {
        $target = $this->orderOrFail($order);
        $input = $request->input('allocations', []);

        $error = StockAllocation::validateSubmission($target->product_id, $input);
        if ($error !== null) {
            return redirect()->route('orders.show', $order)->with('error', $error);
        }

        $toSave = StockAllocation::parseSubmission($target->product_id, $input);
        StockAllocation::saveFromTypedMaps($target->product_id, $toSave);

        return redirect()->route('orders.show', $order)
            ->with('success', '引当を更新しました。');
    }

    public function removeAllocation(Request $request, int $order, int $purchase): RedirectResponse
    {
        $type = $request->query('type', StockAllocation::TYPE_STOCK);
        if (! in_array($type, [StockAllocation::TYPE_STOCK, StockAllocation::TYPE_PO], true)) {
            $type = StockAllocation::TYPE_STOCK;
        }

        $lines = StockAllocation::linesForOrder($order)
            ->filter(fn ($l) => $l->po_id === $purchase && $l->type === $type);

        if ($lines->isEmpty()) {
            $po = DemoData::purchaseOrders()->firstWhere('id', $purchase);
            $label = $po?->code ?? "発注 #{$purchase}";
            $typeLabel = $type === StockAllocation::TYPE_PO ? '発注引当' : '現在庫引当';

            return redirect()->route('orders.show', $order)
                ->with('error', "{$label} の{$typeLabel}はありません。");
        }

        StockAllocation::removeLineFromOrder($order, $purchase, $type);

        $po = DemoData::purchaseOrders()->firstWhere('id', $purchase);
        $label = $po?->code ?? "発注 #{$purchase}";
        $typeLabel = $type === StockAllocation::TYPE_PO ? '発注引当' : '現在庫引当';

        return redirect()->route('orders.show', $order)
            ->with('success', "{$label} の{$typeLabel}を解除しました。");
    }

    public function relinkPurchase(Request $request, int $purchase): RedirectResponse
    {
        $poModel = PurchaseOrder::query()->with('lines')->find($purchase) ?? abort(404);
        $newOrderId = (int) $request->input('new_order_id');
        $newOrder = $this->orderOrFail($newOrderId);
        $productId = $poModel->productIdForLink();

        if ($productId === null || $productId !== (int) $newOrder->product_id) {
            return redirect()->back()
                ->with('error', '品番が異なる受注には付け替えできません。');
        }

        PurchaseOrder::linkToOrder($purchase, $newOrderId);

        return redirect()->back()
            ->with('success', "発注 {$poModel->code} の紐づけ先を {$newOrder->code} に変更しました。（在庫引当の来歴はそのままです）");
    }

    private function orderOrFail(int $orderId): object
    {
        return Order::findForDisplay($orderId) ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromRequest(StoreOrderRequest $request): array
    {
        $productId = (int) $request->input('product_id');
        $mode = (string) $request->input('order_qty_mode', 'tan');

        $resolved = FabricQuantity::resolve(
            $request->input('qty_tan'),
            $request->input('qty_meters'),
            $productId,
            false,
            null,
            $mode === 'meters' ? FabricQuantity::CONTEXT_DEFAULT : FabricQuantity::CONTEXT_ORDER,
        );

        $qtyTan = $mode === 'tan' ? (int) $resolved->qty_tan : 0;
        $qtyMeters = $mode === 'meters'
            ? $resolved->qty_meters
            : QtyHelper::metersFromTan((int) $resolved->qty_tan, $productId);

        return [
            'customer_id' => (int) $request->input('customer_id'),
            'product_id' => $productId,
            'order_qty_mode' => $mode,
            'qty_tan' => $qtyTan,
            'qty_meters' => $qtyMeters,
            'order_date' => $request->input('order_date'),
            'due_date' => $request->input('due_date'),
            'ship_memo' => $request->input('ship_memo', ''),
        ];
    }

    private function generateOrderCode(string $orderDate): string
    {
        $ym = date('ym', strtotime($orderDate));
        $seq = Order::query()->where('code', 'like', "SO-{$ym}-%")->count() + 1;

        return 'SO-'.$ym.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    private function enrichWithAllocation(object $order, bool $withPrice = false): object
    {
        $allocation = StockAllocation::statusForOrder($order);
        $order->allocation_status = $allocation['status'];
        $order->allocation_badge = $allocation['badge_class'];
        $order->shippable_status = $allocation['shippable_status'];
        $order->shippable_badge = $allocation['shippable_badge'];
        $order->allocated = $allocation['allocated'];
        $order->stock_allocated = $allocation['stock_allocated'];
        $order->po_allocated = $allocation['po_allocated'];
        $order->remaining = $allocation['remaining'];
        $order->shippable = $allocation['shippable'];

        if ($withPrice) {
            $order->price = (int) (MasterCatalog::findProduct($order->product_id)?->price ?? 0);
        }

        return $order;
    }
}
