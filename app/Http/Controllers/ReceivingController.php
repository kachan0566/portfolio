<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Fabric\TanRollRecorder;
use App\Services\Receiving\ReceivingRegistrar;
use App\Support\DemoData;
use App\Support\FabricQuantity;
use App\Support\ListSearch;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 入荷実績の一覧表示と発注に対する入荷登録を処理する。
 */
class ReceivingController extends Controller
{
    /**
     * 入荷一覧を検索・絞り込みして表示する。
     *
     * @param  Request  $request  一覧の検索クエリ
     * @return View  receivings.index
     */
    public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $receivings = ListSearch::filter(DemoData::receivings(), $search, [
            'code_fields' => ['code', 'po_code'],
            'date_field' => 'date',
        ]);

        return view('receivings.index', [
            'receivings' => $receivings,
            'search' => $search,
        ]);
    }

    /**
     * 種別ごとの未入荷残がある発注から入荷登録フォームを表示する。
     *
     * @param  Request  $request  type クエリ（糸／生機／製品）
     * @return View  receivings.create
     */
    public function create(Request $request): View
    {
        $type = (string) $request->query('type', PurchaseOrderType::PRODUCT);
        if (! in_array($type, PurchaseOrderType::all(), true)) {
            $type = PurchaseOrderType::PRODUCT;
        }

        $pending = DemoData::purchaseOrders()
            ->filter(fn ($po) => ($po->type ?? '') === $type)
            ->filter(fn ($po) => PurchaseOrderStatus::isActive($po->status ?? ''))
            ->filter(fn ($po) => PurchaseOrder::remainingQtyFor((int) $po->id, $po) > 0)
            ->values();

        $poLinesJson = $this->pendingPoLinesJson($type, $pending);

        return view('receivings.create', [
            'type' => $type,
            'pending' => $pending,
            'poLinesJson' => $poLinesJson,
        ]);
    }

    /**
     * 入荷入力を正規化し、ReceivingRegistrar で登録する。
     *
     * @param  Request  $request  po_id・日付・明細 entries（または旧形式）
     * @return RedirectResponse  receivings.index または create（エラー時）
     */
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
        $entries = $this->normalizeEntries($request, $poType, $poId);

        if ($entries === []) {
            return redirect()->route('receivings.create', ['type' => $poType])
                ->with('error', '入荷する明細行を1行以上選択し、数量を正しく入力してください。');
        }

        $result = ReceivingRegistrar::register($poId, $date, $poType, $entries);

        return redirect()->route('receivings.index')->with('success', $result['message']);
    }

    /**
     * リクエストを ReceivingRegistrar 向けの入荷明細配列に正規化する。
     *
     * @param  Request  $request  entries または旧単一行形式
     * @param  string  $poType  発注種別
     * @param  int  $poId  発注 ID
     * @return list<array{
     *   purchase_order_line_id: int,
     *   qty_kg?: float,
     *   qty_tan?: float,
     *   qty_meters?: int,
     *   roll_lines?: list<array{tan_qty: float, actual_qty_m: float}>
     * }>
     */
    private function normalizeEntries(Request $request, string $poType, int $poId): array
    {
        $rawEntries = $request->input('entries');
        if (is_array($rawEntries) && $rawEntries !== []) {
            return $this->normalizeMultiLineEntries($request, $poType, $poId, $rawEntries);
        }

        return $this->normalizeLegacySingleEntry($request, $poType, $poId);
    }

    /**
     * 複数明細の entries 配列を検証・正規化する。
     *
     * @param  Request  $request  フォーム全体（反ロール等）
     * @param  string  $poType  発注種別
     * @param  int  $poId  発注 ID
     * @param  array<int, mixed>  $rawEntries  entries の生配列
     * @return list<array<string, mixed>>
     */
    private function normalizeMultiLineEntries(Request $request, string $poType, int $poId, array $rawEntries): array
    {
        $entries = [];

        foreach ($rawEntries as $index => $row) {
            if (! is_array($row) || empty($row['selected'])) {
                continue;
            }

            $poLineId = (int) ($row['po_line_id'] ?? 0);
            if ($poLineId <= 0) {
                continue;
            }

            $remaining = PurchaseOrderLine::query()->find($poLineId)?->remainingQty() ?? 0.0;
            if ($remaining <= 0) {
                continue;
            }

            if ($poType === PurchaseOrderType::YARN) {
                $qty = round((float) ($row['qty_kg'] ?? 0), 2);
                if ($qty <= 0 || $qty > $remaining + 0.001) {
                    continue;
                }
                $entries[] = [
                    'purchase_order_line_id' => $poLineId,
                    'qty_kg' => $qty,
                ];
            } else {
                $poLine = PurchaseOrderLine::query()->with(['greige', 'product'])->find($poLineId);
                $productId = $poType === PurchaseOrderType::PRODUCT ? (int) ($poLine?->product_id ?? 0) : null;
                $greigeSku = $poType === PurchaseOrderType::GREIGE
                    ? (string) ($poLine?->greige?->sku ?? '')
                    : null;
                $rawQtyTan = (float) ($row['qty_tan'] ?? 0);
                if (! QtyHelper::isValidTanStep($rawQtyTan)) {
                    continue;
                }

                $resolved = FabricQuantity::resolve(
                    $rawQtyTan,
                    $row['qty_meters'] ?? null,
                    $productId,
                    $poType === PurchaseOrderType::GREIGE,
                    $greigeSku,
                    FabricQuantity::CONTEXT_RECEIVING,
                );
                $qty = $resolved->qty_meters;
                $qtyTan = $resolved->qty_tan;

                if ($qtyTan <= 0 || $qty <= 0 || ! QtyHelper::isValidReceivingTanStep($qtyTan)) {
                    continue;
                }

                $lineRemaining = $remaining > 0 ? $remaining : PurchaseOrder::remainingQtyFor($poId);
                if ($qty > (int) floor($lineRemaining) + 1) {
                    continue;
                }

                $rollLines = $this->normalizeRollLines($row['rolls'] ?? [], $qtyTan, $qty);
                if ($rollLines === []) {
                    continue;
                }

                $entries[] = [
                    'purchase_order_line_id' => $poLineId,
                    'qty_tan' => $qtyTan,
                    'qty_meters' => $qty,
                    'roll_lines' => $rollLines,
                ];
            }
        }

        return $entries;
    }

    /**
     * 旧形式（単一数量・rolls）の入荷入力を1明細に正規化する。
     *
     * @param  Request  $request  qty／qty_tan・rolls など
     * @param  string  $poType  発注種別
     * @param  int  $poId  発注 ID
     * @return list<array<string, mixed>>
     */
    private function normalizeLegacySingleEntry(Request $request, string $poType, int $poId): array
    {
        $po = DemoData::purchaseOrders()->firstWhere('id', $poId);
        $remaining = PurchaseOrder::remainingQtyFor($poId, $po);
        $poLineId = $this->resolveLegacyPoLineId($poId);

        if ($poType === PurchaseOrderType::YARN) {
            $qty = round((float) $request->input('qty'), 2);
            if ($qty <= 0 || $qty > $remaining + 0.001) {
                return [];
            }

            return [[
                'purchase_order_line_id' => $poLineId,
                'qty_kg' => $qty,
            ]];
        }

        $productId = $poType === PurchaseOrderType::PRODUCT ? (int) $po->product_id : null;
        $greigeSku = $poType === PurchaseOrderType::GREIGE
            ? (string) ($po->greige_sku ?? $po->sku)
            : null;
        $rawQtyTan = (float) $request->input('qty_tan');
        if (! QtyHelper::isValidTanStep($rawQtyTan)) {
            return [];
        }

        $resolved = FabricQuantity::resolve(
            $rawQtyTan,
            $request->input('qty_meters', $request->input('qty')),
            $productId,
            $poType === PurchaseOrderType::GREIGE,
            $greigeSku,
            FabricQuantity::CONTEXT_RECEIVING,
        );
        $qty = $resolved->qty_meters;
        $qtyTan = $resolved->qty_tan;

        if ($qtyTan <= 0 || $qty <= 0 || ! QtyHelper::isValidReceivingTanStep($qtyTan)) {
            return [];
        }

        if ($qty > (int) floor($remaining) + 1) {
            return [];
        }

        $rollLines = $this->normalizeRollLines($request->input('rolls', []), $qtyTan, $qty);
        if ($rollLines === []) {
            return [];
        }

        return [[
            'purchase_order_line_id' => $poLineId,
            'qty_tan' => $qtyTan,
            'qty_meters' => $qty,
            'roll_lines' => $rollLines,
        ]];
    }

    /**
     * 旧形式入荷用に発注の先頭明細行 ID を返す。
     *
     * @param  int  $poId  発注 ID
     * @return int  purchase_order_lines.id（無ければ 0）
     */
    private function resolveLegacyPoLineId(int $poId): int
    {
        $line = PurchaseOrder::query()->with('lines')->find($poId)?->lines->sortBy('line_no')->first();

        return (int) ($line?->id ?? 0);
    }

    /**
     * 反ロール入力行を検証し、0.25反刻みの roll_lines に正規化する。
     *
     * @param  mixed  $rollsInput  反数・実測m の入力行配列
     * @param  float  $headerTan  明細ヘッダの反数
     * @param  float  $totalMeters  明細ヘッダの実測 m
     * @return list<array{tan_qty: float, actual_qty_m: float}>
     */
    private function normalizeRollLines(mixed $rollsInput, float $headerTan, float $totalMeters): array
    {
        if (! is_array($rollsInput) || $rollsInput === []) {
            return TanRollRecorder::defaultRollLines($headerTan, $totalMeters);
        }

        $lines = [];
        $tanSum = 0.0;

        foreach ($rollsInput as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rawTanQty = (float) ($row['tan_qty'] ?? 0);
            if (! QtyHelper::isValidTanStep($rawTanQty)) {
                continue;
            }

            $tanQty = QtyHelper::roundTan($rawTanQty);
            $actualM = round((float) ($row['actual_qty_m'] ?? 0), 2);
            if ($tanQty <= 0 || $actualM <= 0) {
                continue;
            }
            $lines[] = ['tan_qty' => $tanQty, 'actual_qty_m' => $actualM];
            $tanSum += $tanQty;
        }

        if ($lines === []) {
            return [];
        }

        if (abs($tanSum - QtyHelper::roundReceivingTan($headerTan)) > 0.01) {
            return [];
        }

        return $lines;
    }

    /**
     * 入荷候補発注ごとの残明細メタを JSON 文字列で返す。
     *
     * @param  string  $type  発注種別
     * @param  Collection<int, object>  $pending  未入荷残のある発注行
     * @return string  po_id => 明細メタ配列 の JSON
     */
    private function pendingPoLinesJson(string $type, $pending): string
    {
        $map = [];
        foreach ($pending as $po) {
            $model = PurchaseOrder::query()
                ->with(['lines.material', 'lines.greige', 'lines.product'])
                ->find((int) $po->id);
            if ($model === null) {
                continue;
            }

            $lines = $model->lines
                ->sortBy('line_no')
                ->map(fn ($line) => $line->toReceivingMeta())
                ->filter(fn ($meta) => ($meta['remaining'] ?? 0) > 0)
                ->values()
                ->all();

            if ($lines !== []) {
                $map[(int) $po->id] = $lines;
            }
        }

        return json_encode($map, JSON_UNESCAPED_UNICODE);
    }
}
