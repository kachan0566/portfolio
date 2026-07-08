<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\FabricTanRoll;
use App\Support\GreigeInventory;
use App\Support\GreigeSupply;
use App\Support\ListSearch;
use App\Support\PurchaseOrderDisplay;
use App\Support\PurchaseOrderLink;
use App\Support\PurchaseOrderOverlay;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use App\Support\YarnInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $statusOptions = PurchaseOrderDisplay::filterOptions();

        $purchases = ListSearch::filter(
            DemoData::purchaseOrders()->map(fn ($po) => $this->enrichPurchase($po)),
            $search,
            [
                'date_field' => 'eta',
                'status_field' => 'stage',
                'status_resolver' => function ($item, $status) {
                    return ($item->stage ?? '') === $status;
                },
            ]
        );

        return view('purchases.index', [
            'purchases' => $purchases,
            'search' => $search,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function show(int $purchase): View
    {
        $purchase = $this->findPurchase($purchase);
        $purchase = $this->enrichPurchase($purchase);

        $yarnShortages = [];
        $greigeShortage = null;

        if ($purchase->type === PurchaseOrderType::GREIGE) {
            $yarnShortages = YarnInventory::shortageMessages(
                $purchase->yarn_requirements ?? [],
                (int) $purchase->id
            );
        }

        if ($purchase->type === PurchaseOrderType::PRODUCT) {
            $greigeShortage = GreigeSupply::shortageMessage(
                (int) $purchase->product_id,
                (int) $purchase->qty_meters,
                (int) $purchase->id
            );
        }

        $tanRolls = collect();
        if ($purchase->type === PurchaseOrderType::GREIGE) {
            $tanRolls = FabricTanRoll::forPo((int) $purchase->id)
                ->filter(fn ($roll) => $roll->stage === FabricTanRoll::STAGE_GREIGE_WIP);
        } elseif ($purchase->type === PurchaseOrderType::PRODUCT) {
            $tanRolls = FabricTanRoll::forPo((int) $purchase->id)
                ->filter(fn ($roll) => $roll->stage === FabricTanRoll::STAGE_PRODUCT);
        }

        return view('purchases.show', [
            'purchase' => $purchase,
            'tanRolls' => $tanRolls,
            'product' => $purchase->type === PurchaseOrderType::PRODUCT
                ? DemoData::findProduct((int) $purchase->product_id)
                : null,
            'greige' => match ($purchase->type) {
                PurchaseOrderType::GREIGE => DemoData::findGreige($purchase->greige_sku ?? $purchase->sku),
                PurchaseOrderType::PRODUCT => DemoData::findGreigeByProductId((int) $purchase->product_id),
                default => null,
            },
            'material' => $purchase->type === PurchaseOrderType::YARN
                ? DemoData::findMaterial((int) $purchase->material_id)
                : null,
            'greigeStock' => $purchase->type === PurchaseOrderType::PRODUCT
                ? GreigeInventory::forPurchase($purchase->id)
                : null,
            'yarnShortages' => $yarnShortages,
            'greigeShortage' => $greigeShortage,
        ]);
    }

    public function create(Request $request): View
    {
        $type = (string) $request->query('type', PurchaseOrderType::PRODUCT);
        if (! in_array($type, PurchaseOrderType::all(), true)) {
            $type = PurchaseOrderType::PRODUCT;
        }

        $sourceOrder = null;
        if ($request->filled('order_id')) {
            $sourceOrder = DemoData::orders()->firstWhere('id', (int) $request->query('order_id'));
            if ($sourceOrder) {
                $type = PurchaseOrderType::PRODUCT;
            }
        }

        $greigeRecipes = DemoData::greigeRecipeData();
        $greigeMeta = DemoData::greiges()
            ->filter(fn ($g) => DemoData::hasGreigeRecipe($g->sku))
            ->mapWithKeys(fn ($g) => [
                $g->sku => [
                    'meters_per_tan' => $g->meters_per_tan,
                    'name' => $g->name,
                    'loss_rate' => DemoData::greigeLossRate($g->sku),
                    'lines' => $greigeRecipes[$g->sku]['lines'] ?? [],
                ],
            ]);

        $productMeta = DemoData::products()->mapWithKeys(fn ($p) => [
            $p->id => [
                'sku' => $p->sku,
                'meters_per_tan' => $p->meters_per_tan,
                'greige_sku' => $p->greige_sku,
            ],
        ]);

        return view('purchases.create', [
            'type' => $type,
            'suppliers' => DemoData::suppliersForPurchaseType($type),
            'shipTos' => DemoData::shipTosForPurchaseType($type),
            'yarnMaterials' => DemoData::yarnMaterials(),
            'greiges' => DemoData::greiges()->filter(fn ($g) => DemoData::hasGreigeRecipe($g->sku))->values(),
            'products' => DemoData::products(),
            'sourceOrder' => $sourceOrder,
            'suggestedMeters' => $request->filled('qty') ? (int) $request->query('qty') : null,
            'greigeMetaJson' => $greigeMeta->toJson(JSON_UNESCAPED_UNICODE),
            'productMetaJson' => $productMeta->toJson(JSON_UNESCAPED_UNICODE),
            'defaultDate' => DemoData::today(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $type = (string) $request->input('type');
        $status = $request->input('save_action') === 'draft'
            ? PurchaseOrderStatus::DRAFT
            : PurchaseOrderStatus::ORDERED;

        $id = PurchaseOrderOverlay::nextId();
        $row = $this->buildRowFromRequest($request, $id, $status);

        if ($type === PurchaseOrderType::GREIGE) {
            $requirements = DemoData::greigeYarnRequirements($row['greige_sku'], $row['qty_meters']);
            if (! YarnInventory::canFulfill($requirements)) {
                return back()->withInput()->withErrors([
                    'qty_tan' => '糸が不足しているため保存できません。'.implode(' ', YarnInventory::shortageMessages($requirements)),
                ]);
            }
            if ($status !== PurchaseOrderStatus::DRAFT) {
                // 確定も同条件（下書き・確定とも足りていれば可）
            }
            YarnInventory::setAllocationsForGreigePo(
                $id,
                YarnInventory::buildAllocationLines($id, $requirements, $status)
            );
        }

        if ($type === PurchaseOrderType::PRODUCT) {
            $msg = GreigeSupply::shortageMessage(
                (int) $row['product_id'],
                (int) $row['qty_meters'],
                $id
            );
            if ($msg !== null) {
                return back()->withInput()->withErrors(['qty_meters' => $msg]);
            }
        }

        if ($type === PurchaseOrderType::PRODUCT && $request->filled('order_id')) {
            PurchaseOrderLink::link($id, (int) $request->input('order_id'));
        }

        PurchaseOrderOverlay::add($row);

        $label = PurchaseOrderStatus::label($type, $status);

        return redirect()->route('purchases.show', $id)
            ->with('success', "発注 {$row['code']} を{$label}で登録しました。");
    }

    public function edit(int $purchase): View
    {
        $purchase = $this->enrichPurchase($this->findPurchase($purchase));
        $type = $purchase->type;

        return view('purchases.edit', [
            'purchase' => $purchase,
            'suppliers' => DemoData::suppliersForPurchaseType($type),
            'shipTos' => DemoData::shipTosForPurchaseType($type),
            'statuses' => PurchaseOrderStatus::labelsFor($type),
            'manualStageEditable' => PurchaseOrderDisplay::manualStageEditable($purchase),
            'manualStage' => PurchaseOrderDisplay::effectiveManualStage($purchase),
            'manualStageOptions' => PurchaseOrderStages::manualOptionsFor($type),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, int $purchase): RedirectResponse
    {
        $target = $this->findPurchase($purchase);
        $type = $target->type ?? PurchaseOrderType::PRODUCT;
        $newStatus = (string) $request->input('status');
        if (! in_array($newStatus, PurchaseOrderStatus::keysFor($type), true)) {
            return back()->withInput()->withErrors(['status' => '無効な状態です。']);
        }

        $patch = [
            'supplier_id' => (int) $request->input('supplier_id'),
            'ship_to_id' => (int) $request->input('ship_to_id'),
            'order_date' => $request->input('order_date'),
            'due_date' => $request->input('due_date'),
            'status' => $newStatus,
        ];

        if ($type === PurchaseOrderType::GREIGE) {
            $requirements = DemoData::greigeYarnRequirements(
                $target->greige_sku ?? $target->sku,
                (int) ($target->qty_meters ?? $target->qty)
            );
            if (in_array($newStatus, [PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::ORDERED], true)
                && ! YarnInventory::canFulfill($requirements, $purchase)) {
                return back()->withInput()->withErrors([
                    'status' => '糸が不足しているためこの状態にできません。',
                ]);
            }
            if ($newStatus === PurchaseOrderStatus::CANCELLED) {
                YarnInventory::releaseGreigePo($purchase);
            } else {
                YarnInventory::setAllocationsForGreigePo(
                    $purchase,
                    YarnInventory::buildAllocationLines($purchase, $requirements, $newStatus)
                );
            }
        }

        if ($type === PurchaseOrderType::PRODUCT
            && in_array($newStatus, [PurchaseOrderStatus::ORDERED, PurchaseOrderStatus::PARTIAL], true)) {
            $msg = GreigeSupply::shortageMessage(
                (int) $target->product_id,
                (int) ($target->qty_meters ?? $target->qty),
                $purchase
            );
            if ($msg !== null) {
                return back()->withInput()->withErrors(['status' => $msg]);
            }
        }

        if ($type === PurchaseOrderType::GREIGE) {
            $received = DemoState::effectiveReceivedQty($purchase, $target);
            if ($received <= 0 && $request->filled('stage')) {
                $newStage = (string) $request->input('stage');
                if ($newStage === PurchaseOrderStages::GREIGE_WEAVING) {
                    DemoState::setPoStage($purchase, $newStage);
                    $patch['stage'] = $newStage;
                }
            }
        }

        if ($type === PurchaseOrderType::PRODUCT) {
            $received = DemoState::effectiveReceivedQty($purchase, $target);
            if ($received <= 0 && $request->filled('stage')) {
                $newStage = PurchaseOrderStages::normalizeProductManualStage((string) $request->input('stage'));
                DemoState::setPoStage($purchase, $newStage);
                $patch['stage'] = $newStage;
            }
        }

        if ($type === PurchaseOrderType::PRODUCT && $request->filled('finish_date')) {
            $patch['finish_date'] = $request->input('finish_date');
        }

        $patch['arrival_memo'] = (string) $request->input('arrival_memo', '');

        PurchaseOrderOverlay::patch($purchase, $patch);

        return redirect()->route('purchases.show', $purchase)
            ->with('success', '発注を更新しました。');
    }

    public function patchArrival(Request $request, int $purchase): RedirectResponse
    {
        $target = $this->findPurchase($purchase);
        $type = $target->type ?? PurchaseOrderType::PRODUCT;

        $validated = $request->validate([
            'expected_arrival_date' => ['nullable', 'date'],
            'arrival_memo' => ['nullable', 'string', 'max:500'],
        ], [], [
            'expected_arrival_date' => '入荷予定日',
            'arrival_memo' => 'メモ',
        ]);

        $patch = [
            'arrival_memo' => (string) ($validated['arrival_memo'] ?? ''),
        ];

        $date = $validated['expected_arrival_date'] ?? null;
        if ($date !== null) {
            if ($type === PurchaseOrderType::PRODUCT) {
                $patch['finish_date'] = $date;
            } else {
                $patch['due_date'] = $date;
            }
        }

        PurchaseOrderOverlay::patch($purchase, $patch);

        $redirectParams = array_filter(
            $request->only(ListSearch::PARAMS),
            fn ($value) => $value !== null && $value !== ''
        );

        return redirect()->route('purchases.index', $redirectParams)
            ->with('success', "発注 {$target->code} の入荷予定を更新しました。");
    }

    public function destroy(int $purchase): RedirectResponse
    {
        $target = $this->findPurchase($purchase);
        if (($target->type ?? '') === PurchaseOrderType::GREIGE) {
            YarnInventory::releaseGreigePo($purchase);
        }

        PurchaseOrderOverlay::patch($purchase, ['status' => PurchaseOrderStatus::CANCELLED]);

        return redirect()->route('purchases.index')
            ->with('success', '発注をキャンセルしました。');
    }

    private function findPurchase(int $id): object
    {
        return DemoData::purchaseOrders()->firstWhere('id', $id) ?? abort(404);
    }

    /** @return array<string, mixed> */
    private function buildRowFromRequest(StorePurchaseOrderRequest $request, int $id, string $status): array
    {
        $type = (string) $request->input('type');
        $row = [
            'id' => $id,
            'code' => $this->generateCode($type),
            'type' => $type,
            'status' => $status,
            'supplier_id' => (int) $request->input('supplier_id'),
            'ship_to_id' => (int) $request->input('ship_to_id'),
            'order_date' => $request->input('order_date'),
            'due_date' => $request->input('due_date'),
            'order_id' => $request->filled('order_id') ? (int) $request->input('order_id') : null,
            'received' => 0,
            'received_kg' => 0.0,
        ];

        if ($type === PurchaseOrderType::YARN) {
            $row['material_id'] = (int) $request->input('material_id');
            $row['qty_kg'] = (float) $request->input('qty_kg');
        } elseif ($type === PurchaseOrderType::GREIGE) {
            $sku = (string) $request->input('greige_sku');
            $greige = DemoData::findGreige($sku);
            $perTan = (int) ($greige->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
            $tan = (float) $request->input('qty_tan');
            $row['greige_sku'] = $sku;
            $row['qty_tan'] = $tan;
            $row['meters_per_tan'] = $perTan;
            $row['qty_meters'] = QtyHelper::metersFromTan($tan, null, true, $sku);
        } else {
            $productId = (int) $request->input('product_id');
            $product = DemoData::findProduct($productId);
            $row['product_id'] = $productId;
            $row['qty_meters'] = (int) $request->input('qty_meters');
            $row['meters_per_tan'] = (int) ($product->meters_per_tan ?? DemoData::METERS_PER_TAN_PRODUCT);
            $row['qty_tan'] = QtyHelper::tanCount($row['qty_meters'], $productId);
        }

        return $row;
    }

    private function generateCode(string $type): string
    {
        $prefix = match ($type) {
            PurchaseOrderType::YARN => 'PO-Y-',
            PurchaseOrderType::GREIGE => 'PO-G-',
            default => 'PO-P-',
        };
        $ym = str_replace('-', '', DemoData::CURRENT_YM);
        $seq = DemoData::purchaseOrders()->where('type', $type)->count() + 1;

        return $prefix.$ym.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    private function enrichPurchase(object $po): object
    {
        $po->material_shortage = match ($po->type ?? '') {
            PurchaseOrderType::GREIGE => ! YarnInventory::canFulfill(
                $po->yarn_requirements ?? [],
                (int) $po->id
            ),
            PurchaseOrderType::PRODUCT => ! GreigeSupply::canFulfillProductMeters(
                (int) ($po->product_id ?? 0),
                (int) ($po->qty_meters ?? $po->qty ?? 0),
                (int) $po->id
            ),
            default => false,
        };

        return $po;
    }
}
