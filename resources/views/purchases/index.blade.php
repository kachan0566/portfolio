@extends('layouts.app')

@section('title', '発注管理')
@section('breadcrumb', '取引 / 発注管理')

@section('content')
    <div class="page-header">
        <div>
            <h1>発注管理</h1>
            <p class="lead">糸・生機・製品の3種類の発注を一覧で確認します。実際の入荷記録は「入荷処理」で登録します。</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('purchases.create', ['type' => 'yarn']) }}" class="btn btn-secondary btn-sm">糸発注</a>
            <a href="{{ route('purchases.create', ['type' => 'greige']) }}" class="btn btn-secondary btn-sm">生機発注</a>
            <a href="{{ route('purchases.create', ['type' => 'product']) }}" class="btn btn-primary">製品発注</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">
            @include('partials.icon', ['name' => 'check'])
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @php
        $orderCount = $purchases->pluck('id')->unique()->count();
    @endphp

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">発注一覧（{{ $purchases->count() }} 行 / {{ $orderCount }} 件）</h2>
            <div class="legend" style="display:flex;gap:12px;flex-wrap:wrap;font-size:12px;">
                @foreach (\App\Support\PurchaseOrderType::all() as $t)
                    <span>{{ \App\Support\PurchaseOrderType::label($t) }} {{ $purchases->where('type', $t)->pluck('id')->unique()->count() }}</span>
                @endforeach
            </div>
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'code' => ['label' => '発注番号', 'placeholder' => 'PO-'],
                'supplier' => ['label' => '依頼先'],
                'sku' => ['label' => '品番'],
                'due' => ['label' => '納期'],
                'status' => [
                    'label' => '工程',
                    'options' => $statusOptions,
                ],
            ],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>種別 / 発注番号</th>
                            <th>品番</th>
                            <th class="num">数量</th>
                            <th>依頼先</th>
                            <th>出荷先</th>
                            <th>納期</th>
                            <th style="min-width:140px;">入荷予定日</th>
                            <th style="min-width:160px;">メモ</th>
                            <th>工程</th>
                            <th>材料不足</th>
                            <th style="width:120px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchases as $po)
                            <tr>
                                <td>
                                    <span class="badge badge-indigo badge--plain">{{ $po->type_label }}</span>
                                    <div class="code-cell" style="margin-top:4px;font-size:12px;">
                                        <a href="{{ route('purchases.show', $po->id) }}" class="link-strong">{{ $po->code }}</a>
                                    </div>
                                </td>
                                <td class="code-cell t-strong">{{ $po->sku }}</td>
                                <td class="num mono">@include('partials.purchase-qty', ['purchase' => $po])</td>
                                <td>{{ $po->supplier }}</td>
                                <td>{{ $po->ship_to }}</td>
                                <td class="mono">{{ $po->eta }}</td>
                                <td>
                                    @include('partials.purchase-arrival-date-inline-form', [
                                        'purchase' => $po,
                                        'search' => $search,
                                    ])
                                </td>
                                <td>
                                    @include('partials.purchase-arrival-memo-inline-form', [
                                        'purchase' => $po,
                                        'search' => $search,
                                    ])
                                </td>
                                <td><span class="badge badge-indigo badge--plain">{{ $po->line_stage ?? $po->stage }}</span></td>
                                <td>
                                    @if ($po->material_shortage)
                                        <span class="badge badge-rose">不足</span>
                                    @else
                                        <span class="t-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <a href="{{ route('purchases.show', $po->id) }}" class="btn btn-secondary btn-sm">詳細</a>
                                        <a href="{{ route('purchases.edit', $po->id) }}" class="btn btn-secondary btn-sm">編集</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
