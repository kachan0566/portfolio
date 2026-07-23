<?php

namespace App\Http\Controllers;

use App\Support\MasterCatalog;

use App\Models\Product;
use App\Models\SalesForecastLine;
use App\Services\Sales\SalesForecastEngine;
use App\Services\Sales\SalesRecognition;
use App\Support\DemoData;
use App\Support\QtyHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesForecastController extends Controller
{
    public function showProduct(Request $request, int $product): View
    {
        $target = MasterCatalog::findProductOrFail($product);
        $ym = $this->resolveYm($request);
        $line = SalesForecastEngine::buildProductLine($product, $target, $ym);
        $detail = SalesForecastEngine::buildDetail($product, $target, $ym);

        return view('sales.forecast-detail', [
            'product' => $target,
            'line' => $line,
            'detail' => $detail,
            'inventoryImpact' => SalesForecastEngine::inventoryImpact($product, $ym),
            'ym' => $ym,
            'monthEndDate' => SalesRecognition::monthEndDate($ym),
        ]);
    }

    public function storeLines(Request $request, int $product): RedirectResponse
    {
        $target = MasterCatalog::findProductOrFail($product);

        $request->validate([
            'target_ym' => ['required', 'string'],
        ]);

        $ym = (string) $request->input('target_ym');
        if (! DemoData::isValidSalesMonth($ym)) {
            abort(422);
        }

        SalesForecastEngine::parseAndSaveProduct($product, $ym, $request->all());

        return redirect()
            ->route('sales.forecast.show', ['product' => $product, 'ym' => $ym])
            ->with('success', "{$target->sku} の見通しを保存しました。");
    }

    public function resetLines(Request $request, int $product): RedirectResponse
    {
        $target = MasterCatalog::findProductOrFail($product);
        $ym = $this->resolveYm($request);

        SalesForecastLine::clearDraftForProduct($product, $ym);

        return redirect()
            ->route('sales.forecast.show', ['product' => $product, 'ym' => $ym])
            ->with('success', "{$target->sku} の見通しをデフォルトに戻しました。");
    }

    public function storeSnapshot(Request $request): RedirectResponse
    {
        $ym = $this->resolveYm($request);
        $result = SalesForecastEngine::build($ym);
        $lines = SalesForecastEngine::snapshotLinePayloads($ym);

        $snapshot = SalesForecastEngine::submitSnapshot(
            $ym,
            '木村 勝也',
            date('Y-m-d'),
            $result->total_sales,
            $result->total_qty,
            $result->total_profit,
            $lines,
        );

        return redirect()
            ->route('sales.index', ['tab' => 'forecast', 'ym' => $ym])
            ->with('success', "提出版 Ver.{$snapshot->version} を保存しました。");
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $ym = $this->resolveYm($request);
        $lines = SalesForecastEngine::exportableLines($ym);
        $filename = "sales_forecast_{$ym}.csv";

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                '品番',
                '見通し出荷総量（反）',
                '見通し出荷総量（m）',
                '出荷実績（反）',
                '出荷実績（m）',
                '残り見通し（反）',
                '残り見通し（m）',
                '見通し売上（円）',
                '見通し粗利（円）',
                '状態',
            ]);

            foreach ($lines as $line) {
                fputcsv($out, [
                    $line->sku,
                    QtyHelper::tanCount($line->total_qty, $line->product_id),
                    $line->total_qty,
                    QtyHelper::tanCount($line->actual_qty, $line->product_id),
                    $line->actual_qty,
                    QtyHelper::tanCount($line->forecast_remaining_qty, $line->product_id),
                    $line->forecast_remaining_qty,
                    $line->total_sales,
                    $line->cost_calculable ? $line->total_profit : '',
                    $line->warning_text !== '' ? $line->warning_text : '正常',
                ]);
            }

            fputcsv($out, [
                '合計',
                QtyHelper::sumTanFromLines($lines, 'total_qty'),
                $lines->sum('total_qty'),
                QtyHelper::sumTanFromLines($lines, 'actual_qty'),
                $lines->sum('actual_qty'),
                QtyHelper::sumTanFromLines($lines, 'forecast_remaining_qty'),
                $lines->sum('forecast_remaining_qty'),
                $lines->sum('total_sales'),
                $lines->where('cost_calculable', true)->sum('total_profit'),
                '',
            ]);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function resolveYm(Request $request): string
    {
        $ym = (string) $request->query('ym', $request->input('ym', DemoData::CURRENT_YM));

        return DemoData::isValidSalesMonth($ym) ? $ym : DemoData::CURRENT_YM;
    }
}
