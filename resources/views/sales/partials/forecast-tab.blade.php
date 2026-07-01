@php
    $f = $forecast;
@endphp

@include('partials.cost-warning', ['warnings' => $costWarnings])

@include('sales.partials.forecast-comparison', [
    'forecast' => $forecast,
    'forecastComparison' => $forecastComparison,
    'ym' => $ym,
    'selectedProduct' => $selectedProduct ?? null,
])

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi__icon tone-indigo">@include('partials.icon', ['name' => 'yen'])</div>
        <div class="kpi__label">{{ $selectedProduct ? '選択品番 見通し売上' : '見通し売上' }}</div>
        <div class="kpi__value">{{ number_format($f->total_sales) }}<span style="font-size:14px;font-weight:600;"> 円</span></div>
    </div>
    <div class="kpi">
        <div class="kpi__icon tone-blue">@include('partials.icon', ['name' => 'package'])</div>
        <div class="kpi__label">見通し出荷数量</div>
        <div class="kpi__value" style="font-size:22px;">
            @include('partials.qty-aggregate', [
                'lines' => ($selectedProduct ?? null) ? $forecast->lines->where('product_id', $selectedProduct->id) : $forecast->lines,
                'qtyKey' => 'total_qty',
                'productId' => $selectedProduct?->id,
            ])
        </div>
    </div>
    <div class="kpi">
        <div class="kpi__icon tone-green">@include('partials.icon', ['name' => 'chart'])</div>
        <div class="kpi__label">見通し粗利</div>
        <div class="kpi__value">
            {{ number_format($f->total_profit) }}<span style="font-size:14px;font-weight:600;"> 円</span>
        </div>
        @if ($f->has_uncalculable_cost)
            <div class="kpi__sub t-muted">算出可能な品番のみ合算</div>
        @endif
    </div>
    <div class="kpi">
        <div class="kpi__icon tone-amber">@include('partials.icon', ['name' => 'chart'])</div>
        <div class="kpi__label">見通し粗利率</div>
        <div class="kpi__value">
            {{ $f->total_sales > 0 ? round($f->total_profit / $f->total_sales * 100, 1) : 0 }}<span style="font-size:14px;font-weight:600;"> %</span>
        </div>
        @if ($f->has_uncalculable_cost)
            <div class="kpi__sub t-muted">算出可能な品番のみ合算</div>
        @endif
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card__head">
        <h2 class="card__title">売上・粗利の推移</h2>
        <div class="legend">
            <span><span class="legend__dot" style="background:#4f46e5;"></span>売上</span>
            <span><span class="legend__dot" style="background:#f59e0b;"></span>製造コスト</span>
            <span><span class="legend__dot" style="background:#10b981;"></span>粗利</span>
            <span class="t-muted" style="font-size:12px;">※ 対象月（{{ $ym }}）は見通し</span>
        </div>
    </div>
    <div class="card__body">
        <canvas id="forecastSalesChart" height="90"></canvas>
    </div>
</div>

<div class="card">
    <div class="card__head">
        <h2 class="card__title">品番別 見通し（{{ $ym }}）</h2>
    </div>
    @include('partials.list-search', [
        'params' => $search,
        'fields' => [
            'sku' => ['label' => '品番'],
        ],
        'hidden' => ['ym' => $ym, 'tab' => 'forecast'],
    ])
    <div class="card__body card__body--flush">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>品番</th>
                        <th class="num">見通し出荷総量</th>
                        <th class="num">出荷実績</th>
                        <th class="num">残り見通し</th>
                        <th class="num">見通し売上</th>
                        <th class="num">見通し粗利</th>
                        <th>状態</th>
                        <th style="width:72px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($forecastLines as $line)
                        @php $lineDiff = $forecastComparison->line_diffs->get($line->product_id); @endphp
                        <tr>
                            <td class="code-cell">
                                <a href="{{ route('recipes.edit', $line->product_id) }}" class="link-strong">{{ $line->sku }}</a>
                            </td>
                            <td class="num mono t-strong">@include('partials.qty', ['qty' => $line->total_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono">@include('partials.qty', ['qty' => $line->actual_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono t-muted">@include('partials.qty', ['qty' => $line->forecast_remaining_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono">{{ number_format($line->total_sales) }} 円</td>
                            <td class="num mono t-strong">
                                @if ($line->cost_calculable)
                                    {{ number_format($line->total_profit) }} 円
                                @else
                                    <span class="t-muted">算出不可</span>
                                @endif
                            </td>
                            <td>
                                @if ($line->warning_text)
                                    <span class="badge badge-amber badge--plain" style="font-size:11px;">{{ $line->warning_text }}</span>
                                @else
                                    <span class="badge badge-green badge--plain" style="font-size:11px;">正常</span>
                                @endif
                                @if ($lineDiff)
                                    <div class="t-muted" style="font-size:11px;margin-top:4px;">
                                        提出版差 売上 {{ $lineDiff->diff_sales >= 0 ? '+' : '' }}{{ number_format($lineDiff->diff_sales) }}円
                                    </div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('sales.forecast.show', ['product' => $line->product_id, 'ym' => $ym]) }}" class="btn btn-secondary btn-sm">詳細</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">条件に一致する品番はありません。</td></tr>
                    @endforelse
                </tbody>
                @if ($forecastLines->isNotEmpty())
                    <tfoot class="data-foot">
                        <tr>
                            <td>合計<span class="t-muted" style="font-size:11px;display:block;">品番別反数の合計</span></td>
                            <td class="num mono">@include('partials.qty-aggregate', ['lines' => $forecastLines, 'qtyKey' => 'total_qty'])</td>
                            <td class="num mono">@include('partials.qty-aggregate', ['lines' => $forecastLines, 'qtyKey' => 'actual_qty'])</td>
                            <td class="num mono">@include('partials.qty-aggregate', ['lines' => $forecastLines, 'qtyKey' => 'forecast_remaining_qty'])</td>
                            <td class="num mono">{{ number_format($forecastLines->sum('total_sales')) }} 円</td>
                            <td class="num mono">{{ number_format($forecastLines->where('cost_calculable', true)->sum('total_profit')) }} 円</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const yen = (v) => '¥' + Number(v).toLocaleString();
    const forecastTrend = @json($forecastTrend);
    const forecastYm = @json($ym);
    new Chart(document.getElementById('forecastSalesChart'), {
        type: 'bar',
        data: {
            labels: forecastTrend.map(t => t.is_forecast ? t.ym + '（見通し）' : t.ym),
            datasets: [
                {
                    label: '売上',
                    data: forecastTrend.map(t => t.sales),
                    backgroundColor: forecastTrend.map(t => t.is_forecast ? '#a5b4fc' : '#4f46e5'),
                    borderRadius: 5,
                    barPercentage: 0.6,
                    categoryPercentage: 0.6,
                },
                {
                    label: '製造コスト',
                    data: forecastTrend.map(t => t.cost),
                    backgroundColor: forecastTrend.map(t => t.is_forecast ? '#fcd34d' : '#f59e0b'),
                    borderRadius: 5,
                    barPercentage: 0.6,
                    categoryPercentage: 0.6,
                },
                {
                    label: '粗利',
                    data: forecastTrend.map(t => t.profit),
                    type: 'line',
                    borderColor: forecastTrend.map(t => t.is_forecast ? '#6ee7b7' : '#10b981'),
                    backgroundColor: forecastTrend.map(t => t.is_forecast ? '#6ee7b7' : '#10b981'),
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointRadius: 3,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (c) => c.dataset.label + ': ' + yen(c.parsed.y),
                    },
                },
            },
            scales: {
                y: {
                    ticks: { callback: (v) => '¥' + (v / 10000) + '万' },
                    grid: { color: '#f1f5f9' },
                },
                x: { grid: { display: false } },
            },
        },
    });
</script>
@endpush
