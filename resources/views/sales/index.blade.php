@extends('layouts.app')

@section('title', '売上・粗利')
@section('breadcrumb', '集計 / 売上・粗利')

@section('content')
    <div class="page-header">
        <div>
            <h1>売上・粗利</h1>
            <p class="lead">出荷実績をもとに、売上・製造コスト・粗利を品番別に集計します（対象月: {{ $ym }}）。</p>
        </div>
        <button class="btn btn-secondary" disabled>@include('partials.icon', ['name' => 'download']) 売上一覧CSV</button>
    </div>

    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi__icon tone-indigo">@include('partials.icon', ['name' => 'yen'])</div>
            <div class="kpi__label">今月売上</div>
            <div class="kpi__value">{{ number_format($totalSales) }}<span style="font-size:14px;font-weight:600;"> 円</span></div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-amber">@include('partials.icon', ['name' => 'layers'])</div>
            <div class="kpi__label">製造コスト</div>
            <div class="kpi__value">{{ number_format($totalCost) }}<span style="font-size:14px;font-weight:600;"> 円</span></div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-green">@include('partials.icon', ['name' => 'chart'])</div>
            <div class="kpi__label">粗利</div>
            <div class="kpi__value">{{ number_format($totalProfit) }}<span style="font-size:14px;font-weight:600;"> 円</span></div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-blue">@include('partials.icon', ['name' => 'chart'])</div>
            <div class="kpi__label">粗利率</div>
            <div class="kpi__value">{{ $totalSales > 0 ? round($totalProfit / $totalSales * 100, 1) : 0 }}<span style="font-size:14px;font-weight:600;"> %</span></div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <h2 class="card__title">売上・粗利の推移</h2>
            <div class="legend">
                <span><span class="legend__dot" style="background:#4f46e5;"></span>売上</span>
                <span><span class="legend__dot" style="background:#f59e0b;"></span>製造コスト</span>
                <span><span class="legend__dot" style="background:#10b981;"></span>粗利</span>
            </div>
        </div>
        <div class="card__body">
            <canvas id="salesChart" height="90"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">品番別 売上・粗利（{{ $ym }}）</h2>
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'sku' => ['label' => '品番'],
            ],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>品番</th>
                            <th class="num">出荷数量</th>
                            <th class="num">売上</th>
                            <th class="num">製造コスト</th>
                            <th class="num">粗利</th>
                            <th class="num">粗利率</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byProduct as $row)
                            <tr>
                                <td class="code-cell t-strong">{{ $row->sku }}</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $row->qty, 'productId' => $row->product_id])</td>
                                <td class="num mono">{{ number_format($row->sales) }} 円</td>
                                <td class="num mono t-muted">{{ number_format($row->cost) }} 円</td>
                                <td class="num mono t-strong">{{ number_format($row->profit) }} 円</td>
                                <td class="num mono">{{ $row->sales > 0 ? round($row->profit / $row->sales * 100, 1) : 0 }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="data-foot">
                        <tr>
                            <td>合計</td>
                            <td class="num mono">@include('partials.qty', ['qty' => $byProduct->sum('qty')])</td>
                            <td class="num mono">{{ number_format($totalSales) }} 円</td>
                            <td class="num mono">{{ number_format($totalCost) }} 円</td>
                            <td class="num mono">{{ number_format($totalProfit) }} 円</td>
                            <td class="num mono">{{ $totalSales > 0 ? round($totalProfit / $totalSales * 100, 1) : 0 }}%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const yen = (v) => '¥' + Number(v).toLocaleString();
    const trend = @json($trend);
    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: trend.map(t => t.ym),
            datasets: [
                { label: '売上', data: trend.map(t => t.sales), backgroundColor: '#4f46e5', borderRadius: 5, barPercentage: 0.6, categoryPercentage: 0.6 },
                { label: '製造コスト', data: trend.map(t => t.sales - t.profit), backgroundColor: '#f59e0b', borderRadius: 5, barPercentage: 0.6, categoryPercentage: 0.6 },
                { label: '粗利', data: trend.map(t => t.profit), type: 'line', borderColor: '#10b981', backgroundColor: '#10b981', tension: 0.35, borderWidth: 2.5, pointRadius: 3 },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + yen(c.parsed.y) } } },
            scales: { y: { ticks: { callback: (v) => '¥' + (v / 10000) + '万' }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } },
        },
    });
</script>
@endpush
