@extends('layouts.app')

@section('title', '発注詳細')
@section('breadcrumb', '取引 / 発注管理 / 詳細')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="code-cell" style="font-size:20px;">{{ $purchase->code }}</h1>
            <p class="lead">
                <span class="badge badge-indigo badge--plain">{{ $purchase->type_label }}</span>
                納期 {{ $purchase->eta }}
                ／ <span class="badge badge-indigo">{{ $purchase->status_label }}</span>
            </p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('purchases.edit', $purchase->id) }}" class="btn btn-secondary">編集</a>
            <a href="{{ route('purchases.index') }}" class="btn btn-secondary">
                @include('partials.icon', ['name' => 'back']) 一覧に戻る
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">
            @include('partials.icon', ['name' => 'check'])
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($purchase->material_shortage)
        <div class="alert alert-danger" style="margin-bottom:16px;">
            材料が不足しています。
            @if (! empty($yarnShortages))
                <ul style="margin:8px 0 0;padding-left:18px;">
                    @foreach ($yarnShortages as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($greigeShortage)
                <p style="margin:8px 0 0;">{{ $greigeShortage }}</p>
            @endif
        </div>
    @endif

    <div class="kpi-grid" style="margin-bottom:16px;">
        <div class="kpi">
            <div class="kpi__icon tone-blue">@include('partials.icon', ['name' => 'cart'])</div>
            <div class="kpi__label">発注数量</div>
            <div class="kpi__value" style="font-size:22px;">@include('partials.purchase-qty', ['purchase' => $purchase])</div>
            @if ($purchase->type === \App\Support\PurchaseOrderType::GREIGE)
                <div class="kpi__sub">標準反長 {{ $purchase->meters_per_tan }}m/反</div>
            @elseif ($purchase->type === \App\Support\PurchaseOrderType::PRODUCT)
                <div class="kpi__sub">標準反長 {{ $purchase->meters_per_tan ?? $product?->meters_per_tan }}m/反</div>
            @endif
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-indigo">@include('partials.icon', ['name' => 'archive'])</div>
            <div class="kpi__label">工程</div>
            <div class="kpi__value" style="font-size:18px;"><span class="badge badge-indigo badge--plain">{{ $purchase->stage }}</span></div>
            <div class="kpi__sub">発注日 {{ $purchase->order_date }}</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon {{ $purchase->material_shortage ? 'tone-rose' : 'tone-green' }}">@include('partials.icon', ['name' => 'archive'])</div>
            <div class="kpi__label">材料不足</div>
            <div class="kpi__value" style="font-size:18px;">
                @if ($purchase->material_shortage)
                    <span class="badge badge-rose">あり</span>
                @else
                    <span class="badge badge-green">なし</span>
                @endif
            </div>
            <div class="kpi__sub">依頼先 {{ $purchase->supplier }}</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:16px;">
        <div class="card">
            <div class="card__head"><h2 class="card__title">発注情報</h2></div>
            <div class="card__body">
                <div class="stat-row">
                    <div class="stat-row__item">
                        <div class="stat-row__label">発注種別</div>
                        <div class="stat-row__value">{{ $purchase->type_label }}</div>
                    </div>
                    <div class="stat-row__item">
                        <div class="stat-row__label">依頼先</div>
                        <div class="stat-row__value">{{ $purchase->supplier }}</div>
                    </div>
                    <div class="stat-row__item">
                        <div class="stat-row__label">出荷先</div>
                        <div class="stat-row__value">{{ $purchase->ship_to }}</div>
                    </div>
                    @if ($purchase->order_code)
                        <div class="stat-row__item">
                            <div class="stat-row__label">関連受注</div>
                            <div class="stat-row__value">
                                <a href="{{ route('orders.show', $purchase->order_id) }}" class="link-strong code-cell">{{ $purchase->order_code }}</a>
                            </div>
                        </div>
                    @endif
                </div>
                <div style="margin-top:16px;">
                    <div class="t-muted" style="font-size:12px;margin-bottom:8px;">発注内容</div>
                    @include('purchases.partials.show-order-lines', [
                        'purchase' => $purchase,
                        'orderLines' => $orderLines,
                    ])
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">入荷状況</h2></div>
            <div class="card__body">
                @include('purchases.partials.show-receiving-summary', [
                    'purchase' => $purchase,
                    'receivingBySku' => $receivingBySku,
                ])
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <h2 class="card__title">発注明細行</h2>
        </div>
        <div class="card__body">
            @include('purchases.partials.show-received-detail', [
                'purchase' => $purchase,
                'receivedDetailRows' => $receivedDetailRows,
            ])
        </div>
    </div>

    @if ($purchase->type === \App\Support\PurchaseOrderType::GREIGE && ! empty($purchase->yarn_requirements))
        <div class="card" style="margin-bottom:16px;">
            <div class="card__head"><h2 class="card__title">必要糸量（ロス率込み）</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>糸品番</th><th class="num">必要量（kg）</th><th class="num">kg/m（理論）</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($purchase->yarn_requirements as $line)
                                <tr>
                                    <td class="code-cell">{{ $line->material_sku }}（{{ $line->material }}）</td>
                                    <td class="num mono">{{ number_format($line->required_kg, 2) }}</td>
                                    <td class="num mono t-muted">{{ $line->qty_per_m }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($purchase->type === \App\Support\PurchaseOrderType::PRODUCT && $greigeStock)
        <div class="card">
            <div class="card__head">
                <h2 class="card__title">染工場 生機在庫（関連ロット）</h2>
                <span class="badge badge-amber badge--plain">仕掛品</span>
            </div>
            <div class="card__body">
                <div class="stat-row">
                    <div class="stat-row__item">
                        <div class="stat-row__label">生機品番</div>
                        <div class="stat-row__value code-cell">{{ $greigeStock->greige_sku }}</div>
                    </div>
                    <div class="stat-row__item">
                        <div class="stat-row__label">数量</div>
                        <div class="stat-row__value mono">@include('partials.qty', ['qty' => $greigeStock->qty_meters, 'isGreige' => true, 'greigeSku' => $greigeStock->greige_sku])</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
