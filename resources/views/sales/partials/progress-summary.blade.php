@php
    $f = $forecast;
    $qtyLines = ($selectedProduct ?? null)
        ? $forecast->lines->where('product_id', $selectedProduct->id)
        : $forecast->lines;
    $actualQty = (float) $qtyLines->sum('actual_qty');
    $totalQty = (float) $qtyLines->sum('total_qty');
    $remainingQty = (float) $qtyLines->sum('forecast_remaining_qty');
    $actualSales = (int) $qtyLines->sum('actual_sales');
    $totalSales = (int) $qtyLines->sum('total_sales');
    $remainingSales = (int) $qtyLines->sum('forecast_remaining_sales');
    $actualProfit = (int) $qtyLines->where('cost_calculable', true)->sum('actual_profit');
    $totalProfit = (int) $qtyLines->where('cost_calculable', true)->sum('total_profit');
    $remainingProfit = (int) $qtyLines->where('cost_calculable', true)->sum('forecast_remaining_profit');
    $progressPct = $totalQty > 0 ? round($actualQty / $totalQty * 100, 1) : 0;
@endphp

<div class="card" style="margin-bottom:16px;">
    <div class="card__head">
        <h2 class="card__title">今月の進捗（{{ $ym }}）</h2>
        <span class="t-muted" style="font-size:13px;">基準: 出荷予定日が {{ $f->month_end_date }} まで</span>
    </div>
    <div class="card__body">
        <div class="kpi-grid" style="margin-bottom:12px;">
            <div class="kpi">
                <div class="kpi__label">出荷数量</div>
                <div class="kpi__value" style="font-size:18px;">
                    @include('partials.qty-aggregate', ['lines' => $qtyLines, 'qtyKey' => 'actual_qty', 'productId' => $selectedProduct?->id])
                    <span style="font-size:13px;"> / @include('partials.qty-aggregate', ['lines' => $qtyLines, 'qtyKey' => 'total_qty', 'productId' => $selectedProduct?->id])</span>
                </div>
                <div class="kpi__sub">
                    実績 @include('partials.qty-aggregate', ['lines' => $qtyLines, 'qtyKey' => 'actual_qty', 'productId' => $selectedProduct?->id])
                    ＋ 見通し @include('partials.qty-aggregate', ['lines' => $qtyLines, 'qtyKey' => 'forecast_remaining_qty', 'productId' => $selectedProduct?->id])
                </div>
            </div>
            <div class="kpi">
                <div class="kpi__label">売上</div>
                <div class="kpi__value" style="font-size:18px;">
                    {{ number_format($totalSales) }}<span style="font-size:13px;"> 円</span>
                </div>
                <div class="kpi__sub">実績 {{ number_format($actualSales) }} ＋ 見通し {{ number_format($remainingSales) }}</div>
            </div>
            <div class="kpi">
                <div class="kpi__label">粗利</div>
                <div class="kpi__value" style="font-size:18px;">
                    {{ number_format($totalProfit) }}<span style="font-size:13px;"> 円</span>
                </div>
                <div class="kpi__sub">実績 {{ number_format($actualProfit) }} ＋ 見通し {{ number_format($remainingProfit) }}</div>
                @if ($f->has_uncalculable_cost && ! ($selectedProduct ?? null))
                    <div class="kpi__sub t-muted">算出可能な品番のみ合算</div>
                @endif
            </div>
        </div>
        <div style="margin-top:4px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:4px;">
                <span>出荷実績 {{ $progressPct }}%</span>
                <span>残り見通し {{ $totalQty > 0 ? round(100 - $progressPct, 1) : 0 }}%</span>
            </div>
            <div style="height:10px;background:#e2e8f0;border-radius:999px;overflow:hidden;display:flex;">
                <div style="width:{{ $totalQty > 0 ? $progressPct : 0 }}%;background:#4f46e5;min-width:0;"></div>
                <div style="flex:1;background:#93c5fd;min-width:0;"></div>
            </div>
        </div>
    </div>
</div>
