<?php

namespace App\Http\Controllers;

use App\Services\Inventory\LongTermInventoryEngine;
use App\Services\Inventory\MonthEndForecastEngine;
use App\Support\DemoData;
use App\Support\ForecastManualAdjustment;
use App\Support\ForecastSnapshot;
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

        return redirect()
            ->route('inventory.index', ['tab' => 'forecast', 'ym' => $request->input('target_ym')])
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

    public function exportCsv(Request $request): StreamedResponse
    {
        $ym = $this->resolveYm($request);
        $result = MonthEndForecastEngine::build($ym);
        $filename = "month_end_forecast_{$ym}.csv";

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                '品番', '現在庫数量（反）', '現在庫数量（m）',
                '月末入荷予定数量', '月末出荷確定数量', '手動調整数量',
                '月末予想在庫数量', '最新製造コスト', '月末予想在庫金額',
                '最古入荷日', '最古在庫月齢', '12か月以上在庫数量', '12か月以上在庫金額', '備考',
            ]);

            $totalQty = 0.0;
            $totalValue = 0;

            foreach ($result->lines as $line) {
                $product = DemoData::findProduct($line->product_id);
                $mpt = $product?->meters_per_tan ?? 50;
                $tan = QtyHelper::tanCount($line->current_stock_qty, $mpt);
                $totalQty += $line->forecast_qty;
                $totalValue += $line->forecast_value;

                fputcsv($out, [
                    $line->sku,
                    $tan,
                    $line->current_stock_qty,
                    $line->inbound_scheduled_qty,
                    $line->outbound_confirmed_qty,
                    $line->manual_adjustment_qty,
                    $line->forecast_qty,
                    $line->unit_cost ?? '',
                    $line->forecast_value,
                    $line->oldest_received_date ?? '',
                    $line->oldest_age_months ?? '',
                    $line->long_term_qty,
                    $line->long_term_value,
                    $line->note,
                ]);
            }

            fputcsv($out, [
                '合計', '', '', '', '', '', $totalQty, '', $totalValue, '', '', '', '', '',
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
