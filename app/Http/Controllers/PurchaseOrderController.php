<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Greige;
use App\Models\GreigeRecipe;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ShipTo;
use App\Models\Supplier;
use App\Services\Inventory\GreigeDyeInput;
use App\Services\Purchase\PurchaseOrderShowData;
use App\Support\Core\BusinessDate;
use App\Support\Core\DemoData;
use App\Support\Inventory\GreigeInventory;
use App\Support\Inventory\GreigeSupply;
use App\Support\Master\ListSearch;
use App\Support\Master\MasterCatalog;
use App\Support\Purchase\PurchaseOrderDisplay;
use App\Support\Purchase\PurchaseOrderLineDisplay;
use App\Support\Purchase\PurchaseOrderStages;
use App\Support\Purchase\PurchaseOrderStatus;
use App\Support\Purchase\PurchaseOrderType;
use App\Support\Qty\QtyHelper;
use App\Support\Yarn\YarnInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 糸・生機・製品の発注登録、表示、更新、削除を処理する。
 */
class PurchaseOrderController extends Controller
{
    /**
     * 発注一覧を検索・絞り込みし、不足フラグを付与して表示する。
     *
     * @param  Request  $request  一覧の検索クエリ
     * @return View  purchases.index
     */
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $statusOptions = PurchaseOrderDisplay::filterOptions();

        $purchases = ListSearch::filter(
            DemoData::purchaseOrderIndexRows()->map(fn ($po) => $this->enrichPurchaseLine($po)),
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

    /**
     * 発注詳細（明細・入荷・糸／生機不足）を表示する。
     *
     * @param  int  $purchase  発注の内部 ID
     * @return View  purchases.show
     */
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
                (float) $purchase->qty_meters,
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
                ? MasterCatalog::findProduct((int) $purchase->product_id)
                : null,
            'greige' => match ($purchase->type) {
                PurchaseOrderType::GREIGE => MasterCatalog::findGreige((string) ($purchase->greige_sku ?? $purchase->sku)),
                PurchaseOrderType::PRODUCT => MasterCatalog::findGreigeByProductId((int) $purchase->product_id),
                default => null,
            },
            'greigeStock' => $purchase->type === PurchaseOrderType::PRODUCT
                ? GreigeInventory::forPurchase($purchase->id)
                : null,
            'yarnShortages' => $yarnShortages,
            'greigeShortage' => $greigeShortage,
        ]);
    }

    /**
     * 種別・受注起点などに応じた発注新規登録フォームを表示する。
     *
     * @param  Request  $request  type・order_id・qty などのクエリ
     * @return View  purchases.create
     */
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
        $greigeMeta = MasterCatalog::greiges()
            ->filter(fn ($g) => GreigeRecipe::existsForSku($g->sku))
            ->mapWithKeys(fn ($g) => [
                $g->sku => [
                    'meters_per_tan' => $g->meters_per_tan,
                    'name' => $g->name,
                    'loss_rate' => DemoData::greigeLossRate($g->sku),
                    'lines' => $greigeRecipes[$g->sku]['lines'] ?? [],
                ],
            ]);

        $productMeta = MasterCatalog::products()->mapWithKeys(fn ($p) => [
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
            'yarnMaterials' => MasterCatalog::yarnMaterials(),
            'greiges' => MasterCatalog::greiges()->filter(fn ($g) => GreigeRecipe::existsForSku($g->sku))->values(),
            'products' => MasterCatalog::products(),
            'sourceOrder' => $sourceOrder,
            'suggestedMeters' => $request->filled('qty') ? (int) $request->query('qty') : null,
            'greigeMetaJson' => $greigeMeta->toJson(JSON_UNESCAPED_UNICODE),
            'productMetaJson' => $productMeta->toJson(JSON_UNESCAPED_UNICODE),
            'defaultDate' => BusinessDate::today(),
        ]);
    }

    /**
     * 検証済み発注明細を登録し、詳細画面へリダイレクトする。
     *
     * @param  StorePurchaseOrderRequest  $request  発注種別・明細・下書き／発注の保存区分
     * @return RedirectResponse  purchases.show
     */
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $type = (string) $request->input('type');
        $status = $request->input('save_action') === 'draft'
            ? PurchaseOrderStatus::DRAFT
            : PurchaseOrderStatus::ORDERED;

        $lines = $request->normalizedLines();

        if ($type === PurchaseOrderType::GREIGE) {
            $totalMeters = round((float) collect($lines)->sum(fn ($line) => (float) ($line['qty_meters'] ?? 0)), 2);
            $requirements = [];
            foreach ($lines as $line) {
                foreach (DemoData::greigeYarnRequirements($line['greige_sku'], (float) $line['qty_meters']) as $req) {
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
                    (float) $line['qty_meters'],
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
                        'qty_tan' => QtyHelper::roundTan((float) $line['qty_tan']),
                        'meters_per_tan' => $line['meters_per_tan'],
                        'qty_meters' => $line['qty_meters'],
                        'received_qty_tan' => 0,
                        'received_qty_m' => 0,
                        'finish_date' => $request->input('due_date'),
                    ]);
                } else {
                    PurchaseOrderLine::query()->create([
                        'purchase_order_id' => $po->id,
                        'line_no' => $lineNo,
                        'product_id' => $line['product_id'],
                        'qty_tan' => QtyHelper::roundTan((float) $line['qty_tan']),
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
                foreach (DemoData::greigeYarnRequirements($line['greige_sku'], (float) $line['qty_meters']) as $req) {
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

    /**
     * 発注編集フォーム（状態・工程・明細行）を表示する。
     *
     * @param  int  $purchase  発注の内部 ID
     * @return View  purchases.edit
     */
    public function edit(int $purchase): View
    {
        $purchase = $this->enrichPurchase($this->findPurchase($purchase));
        $type = $purchase->type;

        $poModel = PurchaseOrder::query()
            ->with([
                'lines.material',
                'lines.greige',
                'lines.product.greige',
            ])
            ->find($purchase->id);

        $lineStageRows = [];
        if ($poModel !== null) {
            foreach ($poModel->lines->sortBy('line_no') as $line) {
                $lineStageRows[] = [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'sku' => $line->skuLabel(),
                    'stage_editable' => PurchaseOrderLineDisplay::manualStageEditable($poModel, $line),
                    'manual_stage' => (string) ($line->stage ?? ''),
                    'current_stage' => PurchaseOrderLineDisplay::label($poModel, $line),
                ];
            }
        }

        return view('purchases.edit', [
            'purchase' => $purchase,
            'suppliers' => Supplier::forPurchaseType($type),
            'shipTos' => ShipTo::forPurchaseType($type),
            'statuses' => PurchaseOrderStatus::labelsFor($type),
            'manualStageEditable' => PurchaseOrderDisplay::manualStageEditable($purchase),
            'manualStage' => (string) ($poModel?->primaryLine()?->stage ?? ''),
            'manualStageOptions' => PurchaseOrderStages::manualOptionsFor($type),
            'lineStageRows' => $lineStageRows,
            'lineStageOptions' => PurchaseOrderStages::manualOptionsFor($type),
        ]);
    }

    /**
     * 発注ヘッダ・状態・工程を更新し、詳細画面へリダイレクトする。
     *
     * @param  UpdatePurchaseOrderRequest  $request  編集済み発注入力
     * @param  int  $purchase  更新対象の発注 ID
     * @return RedirectResponse  purchases.show（検証エラー時は戻る）
     */
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
                (float) ($target->qty_meters ?? $target->qty)
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
                (float) ($target->qty_meters ?? $target->qty),
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
            $received = PurchaseOrder::receivedQtyFor($purchase, $target);
            if ($received <= 0 && $request->filled('stage')) {
                $newStage = (string) $request->input('stage');
                if ($newStage === PurchaseOrderStages::GREIGE_WEAVING) {
                    $model->primaryLine()?->update([
                        'stage' => PurchaseOrderStages::normalizeGreigeManualStage($newStage),
                    ]);
                }
            }
            $this->syncLineStages($model, $request, PurchaseOrderType::GREIGE);
            if ($request->filled('finish_date')) {
                $model->primaryLine()?->update(['finish_date' => $request->input('finish_date')]);
            }
        }

        if ($type === PurchaseOrderType::PRODUCT) {
            $received = PurchaseOrder::receivedQtyFor($purchase, $target);
            if ($received <= 0 && $request->has('stage')) {
                $line = $model->primaryLine();
                if ($line !== null) {
                    $stageValue = $request->input('stage');
                    $error = GreigeDyeInput::applyLineStageChange(
                        $line,
                        $stageValue !== null && $stageValue !== ''
                            ? (string) $stageValue
                            : null
                    );
                    if ($error !== null) {
                        return back()->withInput()->withErrors(['stage' => $error]);
                    }
                }
            }
            if ($request->filled('finish_date')) {
                $model->primaryLine()?->update(['finish_date' => $request->input('finish_date')]);
            }
            $this->syncLineStages($model, $request, PurchaseOrderType::PRODUCT);
        }

        return redirect()->route('purchases.show', $purchase)
            ->with('success', '発注を更新しました。');
    }

    /**
     * 一覧から入荷予定日・メモを部分更新し、一覧へ戻る。
     *
     * @param  Request  $request  expected_arrival_date・arrival_memo・検索クエリの維持用パラメータ
     * @param  int  $purchase  発注の内部 ID
     * @return RedirectResponse  purchases.index
     */
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
            'due_date' => $date !== null && $type === PurchaseOrderType::YARN
                ? $date
                : $model->due_date,
        ]);

        if ($date !== null && in_array($type, [PurchaseOrderType::PRODUCT, PurchaseOrderType::GREIGE], true)) {
            $model->primaryLine()?->update(['finish_date' => $date]);
        }

        $redirectParams = array_filter(
            $request->only(ListSearch::PARAMS),
            fn ($value) => $value !== null && $value !== ''
        );

        return redirect()->route('purchases.index', $redirectParams)
            ->with('success', "発注 {$target->code} の入荷予定を更新しました。");
    }

    /**
     * 発注をキャンセル状態にし、種別に応じた在庫・染投入を戻す。
     *
     * @param  int  $purchase  発注の内部 ID
     * @return RedirectResponse  purchases.index
     */
    public function destroy(int $purchase): RedirectResponse
    {
        $target = $this->findPurchase($purchase);
        if (($target->type ?? '') === PurchaseOrderType::GREIGE) {
            YarnInventory::releaseGreigePo($purchase);
        }
        if (($target->type ?? '') === PurchaseOrderType::PRODUCT) {
            GreigeDyeInput::revertPurchaseOrder($purchase);
        }

        PurchaseOrder::query()->whereKey($purchase)->update([
            'status' => PurchaseOrderStatus::CANCELLED,
        ]);

        return redirect()->route('purchases.index')
            ->with('success', '発注をキャンセルしました。');
    }

    /**
     * デモデータから発注行を取得し、無ければ 404 にする。
     *
     * @param  int  $id  発注の内部 ID
     * @return object  一覧・詳細用の発注表示行
     */
    private function findPurchase(int $id): object
    {
        return DemoData::purchaseOrders()->firstWhere('id', $id) ?? abort(404);
    }

    /**
     * 発注種別に応じた PO コード（年月＋連番）を採番する。
     *
     * @param  string  $type  PurchaseOrderType の値
     * @return string  新規発注コード
     */
    private function generateCode(string $type): string
    {
        $prefix = match ($type) {
            PurchaseOrderType::YARN => 'PO-Y-',
            PurchaseOrderType::GREIGE => 'PO-G-',
            default => 'PO-P-',
        };
        $ym = str_replace('-', '', BusinessDate::currentYm());
        $seq = PurchaseOrder::query()->where('type', $type)->count() + 1;

        return $prefix.$ym.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 詳細用発注に糸／生機の material_shortage フラグを付与する。
     *
     * @param  object  $po  発注表示行
     * @return object  material_shortage を追加した $po
     */
    private function enrichPurchase(object $po): object
    {
        $po->material_shortage = match ($po->type ?? '') {
            PurchaseOrderType::GREIGE => ! YarnInventory::canFulfill(
                $po->yarn_requirements ?? [],
                (int) $po->id
            ),
            PurchaseOrderType::PRODUCT => ! GreigeSupply::canFulfillProductMeters(
                (int) ($po->product_id ?? 0),
                (float) ($po->qty_meters ?? $po->qty ?? 0),
                (int) $po->id
            ),
            default => false,
        };

        return $po;
    }

    /**
     * 一覧行用発注に material_shortage フラグを付与する。
     *
     * @param  object  $po  一覧の発注行
     * @return object  material_shortage を追加した $po
     */
    private function enrichPurchaseLine(object $po): object
    {
        $po->material_shortage = match ($po->type ?? '') {
            PurchaseOrderType::GREIGE => ! YarnInventory::canFulfill(
                $po->yarn_requirements ?? [],
                (int) $po->id
            ),
            PurchaseOrderType::PRODUCT => ! GreigeSupply::canFulfillProductMeters(
                (int) ($po->product_id ?? 0),
                (float) ($po->qty_meters ?? $po->qty ?? 0),
                (int) $po->id
            ),
            default => false,
        };

        return $po;
    }

    /**
     * リクエストの line_stages を編集可能な明細行へ反映する。
     *
     * @param  PurchaseOrder  $model  更新対象の発注
     * @param  Request  $request  line_stages 配列
     * @param  string  $type  発注種別（生機／製品で処理が分岐）
     * @return void
     *
     * @throws ValidationException  製品明細の工程変更が拒否されたとき
     */
    private function syncLineStages(PurchaseOrder $model, Request $request, string $type): void
    {
        $lineStages = $request->input('line_stages', []);
        if (! is_array($lineStages) || $lineStages === []) {
            return;
        }

        $model->loadMissing('lines');
        foreach ($lineStages as $lineId => $stageValue) {
            $line = $model->lines->firstWhere('id', (int) $lineId);
            if ($line === null || ! PurchaseOrderLineDisplay::manualStageEditable($model, $line)) {
                continue;
            }

            if ($type === PurchaseOrderType::GREIGE) {
                $stage = (string) $stageValue;
                $line->update([
                    'stage' => $stage === ''
                        ? null
                        : PurchaseOrderStages::normalizeGreigeManualStage($stage),
                ]);
            }

            if ($type === PurchaseOrderType::PRODUCT && array_key_exists((string) $lineId, $lineStages)) {
                $stageValue = $lineStages[$lineId];
                $error = GreigeDyeInput::applyLineStageChange(
                    $line,
                    $stageValue !== null && $stageValue !== ''
                        ? (string) $stageValue
                        : null
                );
                if ($error !== null) {
                    throw ValidationException::withMessages([
                        "line_stages.{$lineId}" => $error,
                    ]);
                }
            }
        }
    }
}
