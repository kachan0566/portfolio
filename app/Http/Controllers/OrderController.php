<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order\AllocationConversion;
use App\Models\Master\Customer;
use App\Models\Order\Order;
use App\Models\Purchase\PurchaseOrder;
use App\Support\Core\DemoData;
use App\Support\Qty\FabricQuantity;
use App\Support\Master\ListSearch;
use App\Support\Master\MasterCatalog;
use App\Support\Order\OrderProductionStatus;
use App\Support\Inventory\ProductStock;
use App\Support\Qty\QtyHelper;
use App\Support\Shipment\ShipmentPlan;
use App\Support\Inventory\StockAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 受注の一覧・詳細・登録・更新・引当・発注紐づけを処理する。
 */
class OrderController extends Controller
{
    /**
     * 受注一覧を検索・ソートし、引当状況を付与して表示する。
     *
     * @param  Request  $request  一覧の検索クエリ
     * @return View  orders.index
     */
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

    /**
     * 受注詳細と同一品番の在庫・引当・発注・出荷情報を表示する。
     *
     * @param  int  $order  受注の内部 ID（orders.id）
     * @return View  orders.show
     */
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
        $inStockRoll = ProductStock::inStockRollTotals($target->product_id);
        $unallocatedStockQty = StockAllocation::unallocatedStockQuantityForProduct($target->product_id);
        $stockAllocatedQty = (object) [
            'tan' => StockAllocation::stockUsageTanForProduct($target->product_id),
            'meters' => StockAllocation::stockUsageMetersForProduct($target->product_id),
        ];

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
            'unallocatedStockQty' => $unallocatedStockQty,
            'stockAllocatedQty' => $stockAllocatedQty,
            'unallocatedPoRemaining' => $unallocatedPoRemaining,
            'supplyShortage' => $supplyShortage,
            'stockPoOptions' => $poOptions['stock'],
            'poPoOptions' => $poOptions['po'],
            'purchaseOrdersById' => $purchaseOrdersById,
            'inStockRoll' => $inStockRoll,
            'shipmentPlans' => ShipmentPlan::forOrder($target->id),
            'siblingOrders' => $siblingOrders,
        ]);
    }

    /**
     * 受注新規登録フォームを表示する。
     *
     * @return View  orders.create
     */
    public function create(): View
    {
        return view('orders.create', [
            'customers' => Customer::query()->orderBy('id')->get(),
            'products' => MasterCatalog::products(),
        ]);
    }

    /**
     * 検証済み入力で受注を作成し、詳細画面へリダイレクトする。
     *
     * @param  StoreOrderRequest  $request  受注入力（反数または m）
     * @return RedirectResponse  orders.show（just_created 付き）
     */
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

    /**
     * 受注編集フォームを表示する。
     *
     * @param  int  $order  受注の内部 ID
     * @return View  orders.edit
     */
    public function edit(int $order): View
    {
        $target = $this->orderOrFail($order);

        return view('orders.edit', [
            'order' => $target,
            'customers' => Customer::query()->orderBy('id')->get(),
            'products' => MasterCatalog::products(),
        ]);
    }

    /**
     * 検証済み入力で受注を更新し、詳細画面へリダイレクトする。
     *
     * @param  StoreOrderRequest  $request  受注入力
     * @param  int  $order  更新対象の受注 ID
     * @return RedirectResponse  orders.show
     */
    public function update(StoreOrderRequest $request, int $order): RedirectResponse
    {
        Order::query()->findOrFail($order)->update($this->attributesFromRequest($request));

        return redirect()->route('orders.show', $order)
            ->with('success', '受注を更新しました。');
    }

    /**
     * 受注削除の操作を受け付ける（デモのため DB は変更しない）。
     *
     * @param  int  $order  受注の内部 ID（未使用・ルート整合用）
     * @return RedirectResponse  orders.index
     */
    public function destroy(int $order): RedirectResponse
    {
        return redirect()->route('orders.index')
            ->with('success', '受注を削除しました。（テストデータのため保存はされません）');
    }

    /**
     * 未紐づけ発注を受注に紐づけ、条件を満たすとき自動引当を行う。
     *
     * @param  int  $order  受注の内部 ID
     * @param  int  $purchase  発注の内部 ID
     * @return RedirectResponse  orders.show（success または error）
     */
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

    /**
     * 受注に登録された引当をすべて解除する（発注紐づけは維持）。
     *
     * @param  int  $order  受注の内部 ID
     * @return RedirectResponse  orders.show
     */
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

    /**
     * 受注詳細画面から送られた引当フォームを検証し、同一品番の引当をまとめて保存する。
     *
     * 処理の流れ:
     * 1. $order で受注を取得する（存在しなければ 404）
     * 2. リクエストの allocations を StockAllocation で検証する（在庫超過・受注残超過など）
     * 3. 検証エラー時は受注詳細へ戻し、flash の error にメッセージを載せる
     * 4. 成功時は保存用マップに変換し、order_allocations を品番単位で置き換え保存する
     *
     * フォームの allocations は [受注ID][stock|po][発注ID] => 反数 の形。
     * URL の受注は画面の起点だが、保存対象はその品番に属するフォーム内の全受注行。
     *
     * @param  Request  $request  POST 本文。allocations キー（省略時は空配列）を読む
     * @param  int  $order  受注の内部 ID（orders.show へリダイレクト先の特定に使う）
     * @return RedirectResponse  常に orders.show へ。success または error の flash を付与
     */
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

    /**
     * 受注の指定発注・種別（在庫／発注引当）の引当行を1件解除する。
     *
     * @param  Request  $request  クエリ type（stock|po、省略時 stock）
     * @param  int  $order  受注の内部 ID
     * @param  int  $purchase  発注の内部 ID
     * @return RedirectResponse  orders.show
     */
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

    /**
     * 発注の紐づけ先受注を別受注へ付け替える（品番一致時のみ）。
     *
     * @param  Request  $request  new_order_id を含む入力
     * @param  int  $purchase  発注の内部 ID
     * @return RedirectResponse  直前の画面へ戻る
     */
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

    /**
     * 内部 ID で表示用受注を取得し、無ければ 404 にする。
     *
     * @param  int  $orderId  orders.id
     * @return object  Order::findForDisplay の表示行
     */
    private function orderOrFail(int $orderId): object
    {
        return Order::findForDisplay($orderId) ?? abort(404);
    }

    /**
     * 検証済み受注入力を、0.25反・小数mを失わない保存属性へ変換する。
     *
     * @param  StoreOrderRequest  $request  反数またはm指定で検証済みの受注入力
     * @return array<string, mixed> Orderへ保存する属性
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

        $qtyTan = $mode === 'tan' ? $resolved->qty_tan : 0.0;
        $qtyMeters = $mode === 'meters'
            ? $resolved->qty_meters
            : QtyHelper::metersFromTan($resolved->qty_tan, $productId);

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

    /**
     * 受注日に基づき SO-YYMM-連番形式の受注番号を採番する。
     *
     * @param  string  $orderDate  受注日（YYMM 算出用）
     * @return string  新規受注番号
     */
    private function generateOrderCode(string $orderDate): string
    {
        $ym = date('ym', strtotime($orderDate));
        $lastCode = Order::query()
            ->where('code', 'like', "SO-{$ym}-%")
            ->orderByDesc('code')
            ->value('code');
        $seq = $lastCode !== null ? (int) substr((string) $lastCode, -3) + 1 : 1;

        return 'SO-'.$ym.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 表示用受注に引当サマリ（残数・出荷可など）を付与する。
     *
     * @param  object  $order  表示用受注行
     * @param  bool  $withPrice  true のとき単価を付与
     * @return object  引当フィールドを追加した $order
     */
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
