@extends('layouts.app')

@section('title', 'ダッシュボード')
@section('breadcrumb', 'ホーム / ダッシュボード')

@section('content')
    <div class="page-header">
        <div>
            <h1>ダッシュボード</h1>
            <p class="lead">2026年6月の業務状況サマリーです。</p>
        </div>
        <a href="{{ route('sales.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'chart']) 売上・粗利の詳細
        </a>
    </div>

    {{-- KPI カード --}}
    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi__icon tone-indigo">@include('partials.icon', ['name' => 'yen'])</div>
            <div class="kpi__label">今月売上</div>
            <div class="kpi__value">{{ number_format($data['sales']) }}<span style="font-size:14px;font-weight:600;"> 円</span></div>
            <div class="kpi__sub"><span class="kpi__trend up">▲ 前月比 +6.2%</span></div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-blue">@include('partials.icon', ['name' => 'package'])</div>
            <div class="kpi__label">今月出荷数量</div>
            <div class="kpi__value" style="font-size:22px;">@include('partials.qty', ['qty' => $data['shippedQty']])</div>
            <div class="kpi__sub">出荷件数 {{ \App\Support\DemoData::shipments()->count() }} 件</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-green">@include('partials.icon', ['name' => 'chart'])</div>
            <div class="kpi__label">今月粗利</div>
            <div class="kpi__value">{{ number_format($data['profit']) }}<span style="font-size:14px;font-weight:600;"> 円</span></div>
            <div class="kpi__sub">粗利率 {{ $data['sales'] > 0 ? round($data['profit'] / $data['sales'] * 100, 1) : 0 }}%</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-amber">@include('partials.icon', ['name' => 'cart'])</div>
            <div class="kpi__label">未出荷受注</div>
            <div class="kpi__value">{{ $data['unshippedOrders'] }}<span style="font-size:14px;font-weight:600;"> 件</span></div>
            <div class="kpi__sub">未入荷発注 {{ $data['unreceivedPurchaseOrders'] }} 件</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-rose">@include('partials.icon', ['name' => 'alert'])</div>
            <div class="kpi__label">在庫不足商品</div>
            <div class="kpi__value">{{ $data['lowStock']->count() }}<span style="font-size:14px;font-weight:600;"> 件</span></div>
            <div class="kpi__sub">安全在庫を下回る商品</div>
        </div>
    </div>

    {{-- グラフ --}}
    <div class="grid grid-2" style="margin-bottom:16px;">
        <div class="card">
            <div class="card__head">
                <h2 class="card__title">売上・粗利の推移</h2>
                <div class="legend">
                    <span><span class="legend__dot" style="background:#4f46e5;"></span>売上</span>
                    <span><span class="legend__dot" style="background:#10b981;"></span>粗利</span>
                </div>
            </div>
            <div class="card__body">
                <canvas id="trendChart" height="110"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card__head">
                <h2 class="card__title">品番別 今月売上構成</h2>
            </div>
            <div class="card__body">
                <canvas id="productChart" height="180"></canvas>
            </div>
        </div>
    </div>

    {{-- 在庫不足 & 最近の受注 --}}
    <div class="grid grid-equal-2">
        <div class="card">
            <div class="card__head">
                <h2 class="card__title">在庫不足アラート</h2>
                <a href="{{ route('inventory.index') }}" class="btn btn-ghost btn-sm">在庫管理へ</a>
            </div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>品番</th><th class="num">現在庫</th><th class="num">安全在庫</th><th>状態</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($data['lowStock'] as $p)
                                <tr>
                                    <td class="code-cell t-strong">{{ $p->sku }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $p->stock, 'productId' => $p->id])</td>
                                    <td class="num mono t-muted">@include('partials.qty', ['qty' => $p->stock_min, 'productId' => $p->id])</td>
                                    <td><span class="badge badge-rose">要発注</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="t-muted" style="text-align:center;padding:24px;">在庫不足の商品はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2 class="card__title">最近の受注</h2>
                <a href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm">受注管理へ</a>
            </div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>受注番号</th><th>得意先</th><th>品番</th><th class="num">数量</th><th>状態</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $o)
                                <tr>
                                    <td class="code-cell">
                                        <a href="{{ route('orders.show', $o->id) }}" class="link-strong">{{ $o->code }}</a>
                                        @if ($o->is_new_today ?? false)
                                            <span class="badge badge-indigo badge--plain" style="margin-left:6px;">本日受付</span>
                                        @endif
                                    </td>
                                    <td>{{ $o->customer }}</td>
                                    <td>
                                        <span class="code-cell t-strong">{{ $o->sku }}</span>
                                        <div class="t-muted" style="font-size:11.5px;">{{ $o->color }}</div>
                                    </td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $o->qty, 'productId' => $o->product_id])</td>
                                    <td>@include('partials.status', ['status' => $o->status])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const yen = (v) => '¥' + Number(v).toLocaleString();

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: @json($data['trend']->pluck('ym')),
            datasets: [
                {
                    label: '売上', data: @json($data['trend']->pluck('sales')),
                    borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,0.12)',
                    fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#4f46e5',
                },
                {
                    label: '粗利', data: @json($data['trend']->pluck('profit')),
                    borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.10)',
                    fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#10b981',
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + yen(c.parsed.y) } } },
            scales: { y: { ticks: { callback: (v) => '¥' + (v / 10000) + '万' }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } },
        },
    });

    new Chart(document.getElementById('productChart'), {
        type: 'doughnut',
        data: {
            labels: @json($data['salesByProduct']->pluck('sku')),
            datasets: [{
                data: @json($data['salesByProduct']->pluck('sales')),
                backgroundColor: ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                borderWidth: 2, borderColor: '#fff',
            }],
        },
        options: {
            responsive: true,
            cutout: '62%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, font: { size: 11 } } },
                tooltip: { callbacks: { label: (c) => c.label + ': ' + yen(c.parsed) } } },
        },
    });
</script>
@endpush
