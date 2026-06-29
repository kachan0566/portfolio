@extends('layouts.app')

@section('title', '売上・粗利')
@section('breadcrumb', '集計 / 売上・粗利')

@section('content')
    @php
        $queryBase = array_filter([
            'ym' => $ym,
            'sku' => $search['sku'] !== '' ? $search['sku'] : null,
        ], fn ($value) => $value !== null && $value !== '');
        $productSelectUrl = function (int $productId) use ($queryBase, $selectedProductId) {
            if ($selectedProductId === $productId) {
                return route('sales.index', $queryBase);
            }

            return route('sales.index', array_merge($queryBase, ['product_id' => $productId]));
        };
    @endphp

    <div class="page-header">
        <div>
            <h1>売上・粗利</h1>
            <p class="lead">
                出荷実績をもとに、売上・製造コスト・粗利を品番別に集計します（対象月: {{ $ym }}）。
                @if ($selectedProduct)
                    <span class="t-muted">選択中: {{ $selectedProduct->sku }}</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <form method="GET" action="{{ route('sales.index') }}" class="sales-month-form">
                @foreach ($search as $key => $value)
                    @if ($value !== '')
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                @if ($selectedProductId)
                    <input type="hidden" name="product_id" value="{{ $selectedProductId }}">
                @endif
                <label class="sales-month-form__label" for="sales-ym">対象月</label>
                <select class="select" id="sales-ym" name="ym" onchange="this.form.submit()">
                    @foreach ($monthOptions as $optionYm)
                        <option value="{{ $optionYm }}" @selected($optionYm === $ym)>{{ $optionYm }}</option>
                    @endforeach
                </select>
            </form>
            <button class="btn btn-secondary" disabled>@include('partials.icon', ['name' => 'download']) 売上一覧CSV</button>
        </div>
    </div>

    @include('partials.cost-warning', ['warnings' => $costWarnings])

    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi__icon tone-indigo">@include('partials.icon', ['name' => 'yen'])</div>
            <div class="kpi__label">{{ $selectedProduct ? '選択品番 売上' : '対象月売上' }}</div>
            <div class="kpi__value">{{ number_format($totalSales) }}<span style="font-size:14px;font-weight:600;"> 円</span></div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-amber">@include('partials.icon', ['name' => 'layers'])</div>
            <div class="kpi__label">製造コスト</div>
            <div class="kpi__value">
                {{ number_format($totalCost) }}<span style="font-size:14px;font-weight:600;"> 円</span>
            </div>
            @if ($hasUncalculableCost)
                <div class="kpi__sub t-muted">算出可能な品番のみ合算</div>
            @endif
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-green">@include('partials.icon', ['name' => 'chart'])</div>
            <div class="kpi__label">粗利</div>
            <div class="kpi__value">
                {{ number_format($totalProfit) }}<span style="font-size:14px;font-weight:600;"> 円</span>
            </div>
            @if ($hasUncalculableCost)
                <div class="kpi__sub t-muted">算出可能な品番のみ合算</div>
            @endif
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-blue">@include('partials.icon', ['name' => 'chart'])</div>
            <div class="kpi__label">粗利率</div>
            <div class="kpi__value">{{ $totalSales > 0 ? round($totalProfit / $totalSales * 100, 1) : 0 }}<span style="font-size:14px;font-weight:600;"> %</span></div>
            @if ($hasUncalculableCost)
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
            </div>
        </div>
        <div class="card__body">
            <canvas id="salesChart" height="90"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">品番別 売上・粗利（{{ $ym }}）</h2>
            @if ($selectedProductId)
                <a href="{{ route('sales.index', $queryBase) }}" class="btn btn-ghost btn-sm">選択を解除</a>
            @endif
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'sku' => ['label' => '品番'],
            ],
            'hidden' => ['ym' => $ym],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>品番</th>
                            <th class="num">出荷数量</th>
                            <th class="num">単価</th>
                            <th class="num">売上</th>
                            <th class="num">粗利</th>
                            <th class="num">粗利率</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byProduct as $row)
                            <tr
                                class="sales-row{{ $selectedProductId === $row->product_id ? ' is-selected' : '' }}"
                                data-href="{{ $productSelectUrl($row->product_id) }}"
                            >
                                <td class="code-cell">
                                    <a href="{{ route('recipes.edit', $row->product_id) }}" class="link-strong">{{ $row->sku }}</a>
                                </td>
                                <td class="num mono">@include('partials.qty', ['qty' => $row->qty, 'productId' => $row->product_id])</td>
                                <td class="num mono t-muted">{{ number_format($row->price) }} 円/m</td>
                                <td class="num mono">{{ number_format($row->sales) }} 円</td>
                                <td class="num mono t-strong">
                                    @if ($row->profit !== null)
                                        {{ number_format($row->profit) }} 円
                                    @else
                                        <span class="t-muted">算出不可</span>
                                    @endif
                                </td>
                                <td class="num mono">
                                    @if ($row->profit !== null && $row->sales > 0)
                                        {{ round($row->profit / $row->sales * 100, 1) }}%
                                    @else
                                        <span class="t-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="data-foot">
                        <tr>
                            <td>合計</td>
                            <td class="num mono">@include('partials.qty', ['qty' => $allByProduct->sum('qty')])</td>
                            <td class="num mono">—</td>
                            <td class="num mono">{{ number_format($allByProduct->sum('sales')) }} 円</td>
                            <td class="num mono">{{ number_format($allByProduct->where('cost_calculable', true)->sum('profit')) }} 円</td>
                            <td class="num mono">
                                @php $allSales = $allByProduct->sum('sales'); $allProfit = $allByProduct->where('cost_calculable', true)->sum('profit'); @endphp
                                {{ $allSales > 0 ? round($allProfit / $allSales * 100, 1) : 0 }}%
                            </td>
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
                { label: '製造コスト', data: trend.map(t => t.cost), backgroundColor: '#f59e0b', borderRadius: 5, barPercentage: 0.6, categoryPercentage: 0.6 },
                { label: '粗利', data: trend.map(t => t.profit), type: 'line', borderColor: '#10b981', backgroundColor: '#10b981', tension: 0.35, borderWidth: 2.5, pointRadius: 3 },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + yen(c.parsed.y) } } },
            scales: { y: { ticks: { callback: (v) => '¥' + (v / 10000) + '万' }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } },
        },
    });

    document.querySelectorAll('.sales-row[data-href]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                return;
            }
            window.location.href = row.dataset.href;
        });
    });
</script>
@endpush
