@extends('layouts.app')

@section('title', '入荷処理')
@section('breadcrumb', '取引 / 入荷処理')

@section('content')
    @php use App\Support\PurchaseOrderType; @endphp
    <div class="page-header">
        <div>
            <h1>入荷処理</h1>
            <p class="lead">糸（kg）・生機（m）・製品（m）の入荷を記録します。発注番号と紐付き、入荷すると在庫が増えます。</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('receivings.create', ['type' => PurchaseOrderType::YARN]) }}" class="btn btn-secondary btn-sm">糸入荷</a>
            <a href="{{ route('receivings.create', ['type' => PurchaseOrderType::GREIGE]) }}" class="btn btn-secondary btn-sm">生機入荷</a>
            <a href="{{ route('receivings.create', ['type' => PurchaseOrderType::PRODUCT]) }}" class="btn btn-primary">
                @include('partials.icon', ['name' => 'arrow-down']) 入荷を登録
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">入荷履歴（{{ $receivings->count() }} 件）</h2>
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'code' => ['label' => '発注番号', 'placeholder' => 'PO-2606-001'],
                'supplier' => ['label' => '仕入先'],
                'sku' => ['label' => '品番'],
                'due' => ['label' => '入荷日'],
            ],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>入荷番号</th>
                            <th>行</th>
                            <th>種別</th>
                            <th>発注番号</th>
                            <th>仕入先</th>
                            <th>品番</th>
                            <th class="num">入荷数量</th>
                            <th>入荷日</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receivings as $r)
                            @php
                                $poType = $r->po_type ?? PurchaseOrderType::PRODUCT;
                                $poId = \App\Support\DemoData::purchaseOrders()->firstWhere('code', $r->po_code)?->id;
                                $lineNo = $r->line_no ?? 1;
                                $lineCount = $r->line_count ?? 1;
                                $receivingLineId = $r->receiving_line_id ?? null;
                                $canAmendRolls = $receivingLineId
                                    && \App\Support\DemoData::usesReceivingDatabase()
                                    && in_array($poType, [PurchaseOrderType::GREIGE, PurchaseOrderType::PRODUCT], true);
                            @endphp
                            <tr>
                                <td class="code-cell">{{ $r->code }}</td>
                                <td class="mono t-muted">
                                    @if ($lineCount > 1)
                                        {{ $lineNo }}/{{ $lineCount }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><span class="badge badge-indigo badge--plain">{{ PurchaseOrderType::label($poType) }}</span></td>
                                <td>
                                    @if ($poId)
                                        <a href="{{ route('purchases.show', $poId) }}" class="link-strong code-cell" title="元の発注を見る">{{ $r->po_code }}</a>
                                    @else
                                        <span class="code-cell">{{ $r->po_code }}</span>
                                    @endif
                                </td>
                                <td>{{ $r->supplier }}</td>
                                <td class="code-cell t-strong">{{ $r->sku }}</td>
                                <td class="num mono">
                                    <span class="badge badge-green badge--plain">
                                        @if ($poType === PurchaseOrderType::YARN)
                                            +{{ number_format((float) ($r->qty_kg ?? $r->qty ?? 0), 2) }} kg
                                        @elseif ($poType === PurchaseOrderType::GREIGE)
                                            +@include('partials.qty', ['qty' => (int) ($r->qty_meters ?? $r->qty ?? 0), 'isGreige' => true, 'greigeSku' => $r->greige_sku ?? $r->sku])
                                        @else
                                            +@include('partials.qty', ['qty' => (int) ($r->qty ?? 0), 'productId' => $r->product_id ?? null])
                                        @endif
                                    </span>
                                </td>
                                <td class="mono t-muted">{{ $r->date }}</td>
                                <td>
                                    @if ($canAmendRolls)
                                        <a href="{{ route('receiving-lines.show', $receivingLineId) }}" class="btn btn-secondary btn-sm">反修正</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
