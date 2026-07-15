<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Greige;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ShipTo;
use App\Models\Supplier;
use App\Support\DemoData;
use App\Support\DemoState;
use App\Services\Purchase\PurchaseOrderShowData;
use App\Support\GreigeInventory;
use App\Support\GreigeSupply;
use App\Support\ListSearch;
use App\Support\PurchaseOrderDisplay;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use App\Support\YarnInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $purchase = $this->enrichPurchase($this->findPurchase($purchase));

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

        $poModel = PurchaseOrder::query()
            ->with([
                'lines.material',
                'lines.greige',
                'lines.product.greige',
            ])
            ->find($purchase->id);

        $orderLines = $poModel !== null
            ? PurchaseOrderShowData::orderLines($poModel)
            : [];
        $receivingBySku = $poModel !== null
            ? PurchaseOrderShowData::receivingBySku($poModel)
            : [];
        $receivedDetailRows = $poModel !== null
            ? PurchaseOrderShowData::receivedDetailRows($poModel)
            : [];

        return view('purchases.show', [
            'purchase' => $purchase,
            'orderLines' => $orderLines,
            'receivingBySku' => $receivingBySku,
            'receivedDetailRows' => $receivedDetailRows,
            'product' => $purchase->type === PurchaseOrderType::PRODUCT
                ? DemoData::findProduct((int) $purchase->product_id)
                : null,
            'greige' => match ($purchase->type) {
                PurchaseOrderType::GREIGE => DemoData::findGreige($purchase->greige_sku ?? $purchase->sku),
                PurchaseOrderType::PRODUCT => DemoData::findGreigeByProductId((int) $purchase->product_id),
                default => null,
            },
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
            $sourceOrder = Order::findForDisplay((int) $request->query('order_id'));
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
            'suppliers' => Supplier::forPurchaseType($type),
            'shipTos' => ShipTo::forPurchaseType($type),
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

        $lines = $request->normalizedLines();

        if ($type === PurchaseOrderType::GREIGE) {
            $totalMeters = (int) collect($lines)->sum(fn ($line) => (int) ($line['qty_meters'] ?? 0));
            $requirements = [];
            foreach ($lines as $line) {
                foreach (DemoData::greigeYarnRequirements($line['greige_sku'], (int) $line['qty_meters']) as $req) {
                    $requirements[] = $req;
                }
            }
            if (! YarnInventory::canFulfill($requirements)) {
                return back()->withInput()->withErrors([
                    'lines' => '糸が不足しているため保存できません。'.implode(' ', YarnInventory::shortageMessages($requirements)),
                ]);
            }
            unset($totalMeters);
        }

        if ($type === PurchaseOrderType::PRODUCT) {
            foreach ($lines as $index => $line) {
                $msg = GreigeSupply::shortageMessage(
                    (int) $line['product_id'],
                    (int) $line['qty_meters'],
                    null
                );
                if ($msg !== null) {
                    return back()->withInput()->withErrors(["lines.{$index}.qty_meters" => $msg]);
                }
            }
        }

        $purchase = DB::transaction(function () use ($request, $lines, $type, $status) {
            $po = PurchaseOrder::query()->create([
                'code' => $this->generateCode($type),
                'type' => $type,
                'status' => $status,
                'supplier_id' => (int) $request->input('supplier_id'),
                'ship_to_id' => (int) $request->input('ship_to_id'),
                'order_id' => $request->filled('order_id') ? (int) $request->input('order_id') : null,
                'order_date' => $request->input('order_date'),
                'due_date' => $request->input('due_date'),
            ]);

            foreach ($lines as $index => $line) {
                $lineNo = $index + 1;

                if ($type === PurchaseOrderType::YARN) {
                    PurchaseOrderLine::query()->create([
                        'purchase_order_id' => $po->id,
                        'line_no' => $lineNo,
                        'material_id' => $line['material_id'],
                        'qty_kg' => $line['qty_kg'],
                        'received_qty_kg' => 0,
                    ]);
                } elseif ($type === PurchaseOrderType::GREIGE) {
                    $greige = Greige::query()->where('sku', $line['greige_sku'])->firstOrFail();
                    PurchaseOrderLine::query()->create([
                        'purchase_order_id' => $po->id,
                        'line_no' => $lineNo,
                        'greige_id' => $greige->id,
                        'qty_tan' => (int) round((float) $line['qty_tan']),
                        'meters_per_tan' => $line['meters_per_tan'],
                        'qty_meters' => $line['qty_meters'],
                        'received_qty_tan' => 0,
                        'received_qty_m' => 0,
                    ]);
                } else {
                    PurchaseOrderLine::query()->create([
                        'purchase_order_id' => $po->id,
                        'line_no' => $lineNo,
                        'product_id' => $line['product_id'],
                        'qty_tan' => (int) $line['qty_tan'],
                        'qty_meters' => $line['qty_meters'],
                        'received_qty_tan' => 0,
                        'received_qty_m' => 0,
                    ]);
                }
            }

            return $po;
        });

        if ($type === PurchaseOrderType::GREIGE) {
            $requirements = [];
            foreach ($lines as $line) {
                foreach (DemoData::greigeYarnRequirements($line['greige_sku'], (int) $line['qty_meters']) as $req) {
                    $requirements[] = $req;
                }
            }
            YarnInventory::setAllocationsForGreigePo(
                $purchase->id,
                YarnInventory::buildAllocationLines($purchase->id, $requirements, $status)
            );
        }

        $label = PurchaseOrderStatus::label($type, $status);
        $lineCount = count($lines);

        return redirect()->route('purchases.show', $purchase->id)
            ->with('success', "発注 {$purchase->code} を{$label}で登録しました。（明細 {$lineCount} 行）");
    }

    public function edit(int $purchase): View
    {
        $purchase = $this->enrichPurchase($this->findPurchase($purchase));
        $type = $purchase->type;

        return view('purchases.edit', [
            'purchase' => $purchase,
            'suppliers' => Supplier::forPurchaseType($type),
            'shipTos' => ShipTo::forPurchaseType($type),
            'statuses' => PurchaseOrderStatus::labelsFor($type),
            'manualStageEditable' => PurchaseOrderDisplay::manualStageEditable($purchase),
            'manualStage' => PurchaseOrderDisplay::effectiveManualStage($purchase),
            'manualStageOptions' => PurchaseOrderStages::manualOptionsFor($type),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, int $purchase): RedirectResponse
    {
        $target = $this->findPurchase($purchase);
        $model = PurchaseOrder::query()->findOrFail($purchase);
        $type = $target->type ?? PurchaseOrderType::PRODUCT;
        $newStatus = (string) $request->input('status');
        if (! in_array($newStatus, PurchaseOrderStatus::keysFor($type), true)) {
            return back()->withInput()->withErrors(['status' => '無効な状態です。']);
        }

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

        $model->update([
            'supplier_id' => (int) $request->input('supplier_id'),
            'ship_to_id' => (int) $request->input('ship_to_id'),
            'order_date' => $request->input('order_date'),
            'due_date' => $request->input('due_date'),
            'status' => $newStatus,
            'arrival_memo' => (string) $request->input('arrival_memo', ''),
        ]);

        if ($type === PurchaseOrderType::GREIGE) {
            $received = DemoState::effectiveReceivedQty($purchase, $target);
            if ($received <= 0 && $request->filled('stage')) {
                $newStage = (string) $request->input('stage');
                if ($newStage === PurchaseOrderStages::GREIGE_WEAVING) {
                    $model->primaryLine()?->update([
                        'stage' => PurchaseOrderStages::normalizeGreigeManualStage($newStage),
                    ]);
                }
            }
        }

        if ($type === PurchaseOrderType::PRODUCT) {
            $received = DemoState::effectiveReceivedQty($purchase, $target);
            $productPatch = [];
            if ($received <= 0 && $request->filled('stage')) {
                $productPatch['stage'] = PurchaseOrderStages::normalizeProductManualStage(
                    (string) $request->input('stage')
                );
            }
            if ($request->filled('finish_date')) {
                $productPatch['finish_date'] = $request->input('finish_date');
            }
            if ($productPatch !== []) {
                $model->primaryLine()?->update($productPatch);
            }
        }

        return redirect()->route('purchases.show', $purchase)
            ->with('success', '発注を更新しました。');
    }

    public function patchArrival(Request $request, int $purchase): RedirectResponse
    {
        $target = $this->findPurchase($purchase);
        $model = PurchaseOrder::query()->findOrFail($purchase);
        $type = $target->type ?? PurchaseOrderType::PRODUCT;

        $validated = $request->validate([
            'expected_arrival_date' => ['nullable', 'date'],
            'arrival_memo' => ['nullable', 'string', 'max:500'],
        ], [], [
            'expected_arrival_date' => '入荷予定日',
            'arrival_memo' => 'メモ',
        ]);

        $date = $validated['expected_arrival_date'] ?? null;
        $model->update([
            'arrival_memo' => (string) ($validated['arrival_memo'] ?? ''),
            'due_date' => $date !== null && $type !== PurchaseOrderType::PRODUCT
                ? $date
                : $model->due_date,
        ]);

        if ($date !== null && $type === PurchaseOrderType::PRODUCT) {
            $model->primaryLine()?->update(['finish_date' => $date]);
        }

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

        PurchaseOrder::query()->whereKey($purchase)->update([
            'status' => PurchaseOrderStatus::CANCELLED,
        ]);

        return redirect()->route('purchases.index')
            ->with('success', '発注をキャンセルしました。');
    }

    private function findPurchase(int $id): object
    {
        return DemoData::purchaseOrders()->firstWhere('id', $id) ?? abort(404);
    }

    private function generateCode(string $type): string
    {
        $prefix = match ($type) {
            PurchaseOrderType::YARN => 'PO-Y-',
            PurchaseOrderType::GREIGE => 'PO-G-',
            default => 'PO-P-',
        };
        $ym = str_replace('-', '', DemoData::CURRENT_YM);
        $seq = PurchaseOrder::query()->where('type', $type)->count() + 1;

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
