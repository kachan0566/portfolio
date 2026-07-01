@extends('layouts.app')

@section('title', '出荷処理')
@section('breadcrumb', '取引 / 出荷処理')

@section('content')
    <div class="page-header">
        <div>
            <h1>出荷処理</h1>
            <p class="lead">実際に送ったモノを1件ずつ記録する画面です。受注番号と紐付き、出荷すると在庫が減って売上に計上されます。1件の受注に対して複数回の出荷（分納）も記録できます。</p>
        </div>
        <a href="{{ route('shipments.create') }}" class="btn btn-primary">
            @include('partials.icon', ['name' => 'arrow-up']) 出荷を登録
        </a>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">出荷履歴（{{ $shipments->count() }} 件）</h2>
            <span class="badge badge-indigo badge--plain">出荷金額合計 {{ number_format($shipments->sum('amount')) }} 円</span>
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'code' => ['label' => '受注番号', 'placeholder' => 'SO-2606-001'],
                'customer' => ['label' => '得意先'],
                'sku' => ['label' => '品番'],
                'due' => ['label' => '納期'],
            ],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>出荷番号</th>
                            <th>受注元</th>
                            <th>品番</th>
                            <th>カラー</th>
                            <th class="num">数量</th>
                            <th>納期</th>
                            <th>出荷日</th>
                            <th>出荷先</th>
                            <th>備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shipments as $s)
                            <tr>
                                <td class="code-cell">{{ $s->code }}</td>
                                <td>
                                    @php $orderId = \App\Support\DemoData::orders()->firstWhere('code', $s->order_code)?->id; @endphp
                                    @if ($orderId)
                                        <a href="{{ route('orders.edit', $orderId) }}" class="link-strong code-cell" title="元の受注を見る">{{ $s->order_code }}</a>
                                    @else
                                        <span class="code-cell">{{ $s->order_code }}</span>
                                    @endif
                                    <div class="t-muted" style="font-size:11.5px;">{{ $s->customer }}</div>
                                </td>
                                <td class="code-cell t-strong">{{ $s->sku }}</td>
                                <td>{{ $s->color }}</td>
                                <td class="num mono"><span class="badge badge-rose badge--plain">-@include('partials.qty', ['qty' => $s->qty, 'productId' => $s->product_id])</span></td>
                                <td class="mono">{{ $s->due_date }}</td>
                                <td class="mono t-muted">{{ $s->date }}</td>
                                <td style="font-size:12.5px;">{{ $s->ship_to }}</td>
                                <td class="t-muted" style="font-size:12px;">{{ $s->note ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
