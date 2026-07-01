@extends('layouts.app')

@section('title', '発注管理')
@section('breadcrumb', '取引 / 発注管理')

@section('content')
    <div class="page-header">
        <div>
            <h1>発注管理</h1>
            <p class="lead">仕入先への発注と、生産が今どこまで進んだか（8段階）を“ながめる”画面です。実際にモノが届いた記録は「入荷処理」で登録します。</p>
        </div>
        <a href="{{ route('purchases.create') }}" class="btn btn-primary">
            @include('partials.icon', ['name' => 'plus']) 発注を登録
        </a>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">発注一覧（{{ $purchases->count() }} 件）</h2>
            <div class="legend">
                @foreach (\App\Support\DemoData::PO_STAGES as $st)
                    <span>{{ $st }} {{ $purchases->where('stage', $st)->count() }}</span>
                @endforeach
            </div>
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'code' => ['label' => '発注番号', 'placeholder' => 'PO-2606-001'],
                'customer' => ['label' => '得意先'],
                'supplier' => ['label' => '仕入先'],
                'sku' => ['label' => '品番'],
                'due' => ['label' => '入荷予定'],
                'status' => [
                    'label' => '進捗段階',
                    'options' => collect(\App\Support\DemoData::PO_STAGES)->mapWithKeys(fn ($s) => [$s => $s])->all(),
                ],
            ],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>発注番号</th>
                            <th>受注番号</th>
                            <th>得意先</th>
                            <th>仕入先</th>
                            <th>品番</th>
                            <th class="num">数量</th>
                            <th>進捗段階</th>
                            <th>発注日</th>
                            <th>入荷予定</th>
                            <th>上がり予定</th>
                            <th>先方連絡予定</th>
                            <th style="width:88px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchases as $po)
                            @php $customerId = \App\Support\DemoData::customers()->firstWhere('name', $po->customer)?->id; @endphp
                            <tr>
                                <td class="code-cell">{{ $po->code }}</td>
                                <td class="code-cell">
                                    @if ($po->order_code)
                                        <a href="{{ route('orders.show', $po->order_id) }}" class="link-strong">{{ $po->order_code }}</a>
                                    @else
                                        <span class="t-muted">—（紐づけなし）</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($customerId)
                                        <a href="{{ route('customers.show', $customerId) }}" class="link-strong">{{ $po->customer }}</a>
                                    @else
                                        {{ $po->customer }}
                                    @endif
                                </td>
                                <td>{{ $po->supplier }}</td>
                                <td class="code-cell t-strong">{{ $po->sku }}</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $po->qty, 'productId' => $po->product_id])</td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div class="progress">
                                            <div class="progress__bar {{ $po->progress >= 100 ? 'full' : ($po->progress === 0 ? 'none' : '') }}" style="width:{{ $po->progress }}%;"></div>
                                        </div>
                                        <span class="badge badge-indigo badge--plain" style="font-size:10.5px;">{{ $po->stage }}</span>
                                    </div>
                                </td>
                                <td class="mono t-muted">{{ $po->order_date }}</td>
                                <td class="mono">{{ $po->eta }}</td>
                                <td class="mono">{{ $po->finish_date }}</td>
                                <td class="mono">{{ $po->contact_date }}</td>
                                <td>
                                    <a href="{{ route('purchases.edit', $po->id) }}" class="btn btn-secondary btn-sm">編集</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
