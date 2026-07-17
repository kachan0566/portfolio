<?php

namespace App\Http\Controllers;

use App\Services\Inventory\CombinedMonthEndForecastEngine;
use App\Services\Inventory\ForecastSubmissionCoordinator;
use App\Services\Inventory\GreigeMonthEndForecastEngine;
use App\Services\Inventory\LongTermInventoryEngine;
use App\Services\Inventory\MonthEndForecastEngine;
use App\Support\DemoData;
use App\Support\ForecastManualAdjustment;
use App\Support\ForecastSnapshot;
use App\Support\GreigeForecastManualAdjustment;
use App\Support\GreigeForecastSnapshot;
use App\Support\QtyHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryForecastController extends Controller
{
    public function showProduct(Request $request, int $product): View
    {
        $target = DemoData::findProduct($product) ?? abort(404);
        $ym = $this->resolveYm($request);
        $line = MonthEndForecastEngine::buildLine(
            $product,
            $target,
            $ym,
            MonthEndForecastEngine::monthEndDate($ym)
        );

        return view('inventory.forecast-detail', [
            'product' => $target,
            'line' => $line,
            'ym' => $ym,
            'monthEndDate' => MonthEndForecastEngine::monthEndDate($ym),
            'forecastSummary' => MonthEndForecastEngine::summarizeLines(collect([$line]), $ym),
            'unshippedOrders' => MonthEndForecastEngine::unshippedOrdersForProduct($product),
        ]);
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer'],
            'target_ym' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'direction' => ['required', 'in:increase,decrease'],
            'reason' => ['required', 'string', 'max:500'],
            'redirect' => ['nullable', 'in:detail'],
        ]);

        $productId = (int) $request->input('product_id');
        if (! DemoData::findProduct($productId)) {
            abort(404);
        }

        ForecastManualAdjustment::add(
            $productId,
            (string) $request->input('target_ym'),
            (float) $request->input('qty'),
            (string) $request->input('direction'),
            (string) $request->input('reason'),
            '木村 勝也'
        );

        $targetYm = (string) $request->input('target_ym');

        if ($request->input('redirect') === 'detail') {
            return redirect()
                ->route('inventory.forecast.show', ['product' => $productId, 'ym' => $targetYm])
                ->with('success', '手動調整を登録しました。');
        }

        return redirect()
            ->route('inventory.index', ['tab' => 'forecast', 'ym' => $targetYm])
            ->with('success', '手動調整を登録しました。');
    }

    public function storeSnapshot(Request $request): RedirectResponse
    {
        $ym = $this->resolveYm($request);
        $result = MonthEndForecastEngine::build($ym);

        $lines = $result->lines->map(fn ($line) => [
            'product_id' => $line->product_id,
            'sku' => $line->sku,
            'current_stock_qty' => $line->current_stock_qty,
            'inbound_scheduled_qty' => $line->inbound_scheduled_qty,
            'outbound_confirmed_qty' => $line->outbound_confirmed_qty,
            'manual_adjustment_qty' => $line->manual_adjustment_qty,
            'forecast_qty' => $line->forecast_qty,
            'unit_cost' => $line->unit_cost,
            'forecast_value' => $line->forecast_value,
            'long_term_qty' => $line->long_term_qty,
            'long_term_value' => $line->long_term_value,
            'oldest_received_date' => $line->oldest_received_date,
            'oldest_age_months' => $line->oldest_age_months,
            'note' => $line->note,
        ])->all();

        $snapshot = ForecastSnapshot::save([
            'target_ym' => $ym,
            'base_date' => date('Y-m-d'),
            'created_by' => '木村 勝也',
            'total_forecast_value' => $result->forecast_value,
            'total_long_term_value' => $result->long_term_value,
        ], $lines);

        return redirect()
            ->route('inventory.index', ['tab' => 'forecast', 'ym' => $ym])
            ->with('success', "提出版 Ver.{$snapshot->version} を保存しました。");
    }

    public function storeGreigeAdjustment(Request $request): RedirectResponse
    {
        $request->validate([
            'greige_sku' => ['required', 'string'],
            'target_ym' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'direction' => ['required', 'in:increase,decrease'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $greigeSku = (string) $request->input('greige_sku');
        if (DemoData::findGreige($greigeSku) === null) {
            abort(404);
        }

        GreigeForecastManualAdjustment::add(
            $greigeSku,
            (string) $request->input('target_ym'),
            (float) $request->input('qty'),
            (string) $request->input('direction'),
            (string) $request->input('reason'),
            '木村 勝也'
        );

        return redirect()
            ->route('inventory.index', ['tab' => 'greige_forecast', 'ym' => $request->input('target_ym')])
            ->with('success', '手動調整を登録しました。');
    }

    public function storeGreigeSnapshot(Request $request): RedirectResponse
    {
        $ym = $this->resolveYm($request);
        $result = GreigeMonthEndForecastEngine::build($ym);

        $lines = $result->lines->map(fn ($line) => [
            'greige_sku' => $line->greige_sku,
            'sku' => $line->sku,
            'current_stock_qty' => $line->current_stock_qty,
            'inbound_scheduled_qty' => $line->inbound_scheduled_qty,
            'outbound_scheduled_qty' => $line->outbound_scheduled_qty,
            'manual_adjustment_qty' => $line->manual_adjustment_qty,
            'forecast_qty' => $line->forecast_qty,
            'unit_cost' => $line->unit_cost,
            'forecast_value' => $line->forecast_value,
            'long_term_qty' => $line->long_term_qty,
            'long_term_value' => $line->long_term_value,
            'oldest_received_date' => $line->oldest_received_date,
            'oldest_age_months' => $line->oldest_age_months,
        ])->all();

        $snapshot = GreigeForecastSnapshot::save([
            'target_ym' => $ym,
            'base_date' => date('Y-m-d'),
            'created_by' => '木村 勝也',
            'total_forecast_value' => $result->forecast_value,
            'total_long_term_value' => $result->long_term_value,
        ], $lines);

        return redirect()
            ->route('inventory.index', ['tab' => 'greige_forecast', 'ym' => $ym])
            ->with('success', "提出版 Ver.{$snapshot->version} を保存しました。");
    }

    public function storeCombinedSnapshot(Request $request): RedirectResponse
    {
        $ym = $this->resolveYm($request);
        $result = ForecastSubmissionCoordinator::saveUnified($ym);

        return redirect()
            ->route('inventory.index', ['tab' => 'forecast_combined', 'ym' => $ym])
            ->with('success', "提出版 Ver.{$result->version} を保存しました（製品・生機・合算）。");
    }

    public function exportGreigeCsv(Request $request): StreamedResponse
    {
        $ym = $this->resolveYm($request);
        $result = GreigeMonthEndForecastEngine::build($ym);
        $filename = "greige_month_end_forecast_{$ym}.csv";

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                '生機品番',
                '現在庫数量（反）', '現在庫数量（m）',
                '入荷予定（反）', '入荷予定（m）',
                '染機投入予定（反）', '染機投入予定（m）',
                '手動調整（反）', '手動調整（m）',
                '月末予想在庫（反）', '月末予想在庫（m）',
                '生機単価', '月末予想在庫金額',
                '最古入荷日', '最古在庫月齢',
                '12か月以上在庫（反）', '12か月以上在庫（m）', '12か月以上在庫金額',
            ]);

            foreach ($result->lines as $line) {
                $greigeSku = (string) $line->greige_sku;

                fputcsv($out, [
                    $line->sku,
                    QtyHelper::tanCount($line->current_stock_qty, null, true, $greigeSku),
                    $line->current_stock_qty,
                    QtyHelper::tanCount($line->inbound_scheduled_qty, null, true, $greigeSku),
                    $line->inbound_scheduled_qty,
                    QtyHelper::tanCount($line->outbound_scheduled_qty, null, true, $greigeSku),
                    $line->outbound_scheduled_qty,
                    QtyHelper::tanCount($line->manual_adjustment_qty, null, true, $greigeSku),
                    $line->manual_adjustment_qty,
                    QtyHelper::tanCount($line->forecast_qty, null, true, $greigeSku),
                    $line->forecast_qty,
                    $line->unit_cost ?? '',
                    $line->forecast_value,
                    $line->oldest_received_date ?? '',
                    $line->oldest_age_months ?? '',
                    QtyHelper::tanCount($line->long_term_qty, null, true, $greigeSku),
                    $line->long_term_qty,
                    $line->long_term_value,
                ]);
            }

            fputcsv($out, [
                '合計',
                QtyHelper::sumTanFromLines($result->lines, 'current_stock_qty', 'product_id', true, 'greige_sku'),
                $result->lines->sum('current_stock_qty'),
                QtyHelper::sumTanFromLines($result->lines, 'inbound_scheduled_qty', 'product_id', true, 'greige_sku'),
                $result->lines->sum('inbound_scheduled_qty'),
                QtyHelper::sumTanFromLines($result->lines, 'outbound_scheduled_qty', 'product_id', true, 'greige_sku'),
                $result->lines->sum('outbound_scheduled_qty'),
                QtyHelper::sumTanFromLines($result->lines, 'manual_adjustment_qty', 'product_id', true, 'greige_sku'),
                $result->lines->sum('manual_adjustment_qty'),
                QtyHelper::sumTanFromLines($result->lines, 'forecast_qty', 'product_id', true, 'greige_sku'),
                $result->lines->sum('forecast_qty'),
                '',
                $result->lines->sum('forecast_value'),
                '', '',
                QtyHelper::sumTanFromLines($result->lines, 'long_term_qty', 'product_id', true, 'greige_sku'),
                $result->lines->sum('long_term_qty'),
                $result->lines->sum('long_term_value'),
            ]);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $ym = $this->resolveYm($request);
        $result = MonthEndForecastEngine::build($ym);
        $filename = "month_end_forecast_{$ym}.csv";

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                '品番',
                '現在庫数量（反）', '現在庫数量（m）',
                '入荷予定（反）', '入荷予定（m）',
                '出荷確定（反）', '出荷確定（m）',
                '手動調整（反）', '手動調整（m）',
                '月末予想在庫（反）', '月末予想在庫（m）',
                '最新製造コスト', '月末予想在庫金額',
                '最古入荷日', '最古在庫月齢',
                '12か月以上在庫（反）', '12か月以上在庫（m）', '12か月以上在庫金額',
                '備考',
            ]);

            foreach ($result->lines as $line) {
                $productId = (int) $line->product_id;

                fputcsv($out, [
                    $line->sku,
                    QtyHelper::tanCount($line->current_stock_qty, $productId),
                    $line->current_stock_qty,
                    QtyHelper::tanCount($line->inbound_scheduled_qty, $productId),
                    $line->inbound_scheduled_qty,
                    QtyHelper::tanCount($line->outbound_confirmed_qty, $productId),
                    $line->outbound_confirmed_qty,
                    QtyHelper::tanCount($line->manual_adjustment_qty, $productId),
                    $line->manual_adjustment_qty,
                    QtyHelper::tanCount($line->forecast_qty, $productId),
                    $line->forecast_qty,
                    $line->unit_cost ?? '',
                    $line->forecast_value,
                    $line->oldest_received_date ?? '',
                    $line->oldest_age_months ?? '',
                    QtyHelper::tanCount($line->long_term_qty, $productId),
                    $line->long_term_qty,
                    $line->long_term_value,
                    $line->note,
                ]);
            }

            fputcsv($out, [
                '合計',
                QtyHelper::sumTanFromLines($result->lines, 'current_stock_qty'),
                $result->lines->sum('current_stock_qty'),
                QtyHelper::sumTanFromLines($result->lines, 'inbound_scheduled_qty'),
                $result->lines->sum('inbound_scheduled_qty'),
                QtyHelper::sumTanFromLines($result->lines, 'outbound_confirmed_qty'),
                $result->lines->sum('outbound_confirmed_qty'),
                QtyHelper::sumTanFromLines($result->lines, 'manual_adjustment_qty'),
                $result->lines->sum('manual_adjustment_qty'),
                QtyHelper::sumTanFromLines($result->lines, 'forecast_qty'),
                $result->lines->sum('forecast_qty'),
                '',
                $result->lines->sum('forecast_value'),
                '', '',
                QtyHelper::sumTanFromLines($result->lines, 'long_term_qty'),
                $result->lines->sum('long_term_qty'),
                $result->lines->sum('long_term_value'),
                '',
            ]);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function longTermDetail(int $product): View
    {
        $target = DemoData::findProduct($product) ?? abort(404);
        $line = LongTermInventoryEngine::buildLine(
            $product,
            $target,
            DemoData::today(),
            DemoData::CURRENT_YM
        );

        return view('inventory.long-term-detail', [
            'product' => $target,
            'line' => $line,
            'asOfDate' => DemoData::today(),
        ]);
    }

    private function resolveYm(Request $request): string
    {
        $ym = (string) $request->query('ym', $request->input('ym', DemoData::CURRENT_YM));

        return DemoData::isValidForecastMonth($ym) ? $ym : DemoData::CURRENT_YM;
    }
}
