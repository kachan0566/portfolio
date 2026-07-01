@extends('layouts.app')

@section('title', '入荷処理')
@section('breadcrumb', '取引 / 入荷処理')

@section('content')
    <div class="page-header">
        <div>
            <h1>入荷処理</h1>
            <p class="lead">実際に届いたモノを1件ずつ記録する画面です。発注番号と紐付き、入荷すると在庫が増えます。1件の発注に対して複数回の入荷（分納）も記録できます。</p>
        </div>
        <a href="{{ route('receivings.create') }}" class="btn btn-primary">
            @include('partials.icon', ['name' => 'arrow-down']) 入荷を登録
        </a>
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
                            <th>発注番号</th>
                            <th>仕入先</th>
                            <th>品番</th>
                            <th class="num">入荷数量</th>
                            <th>入荷日</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receivings as $r)
                            <tr>
                                <td class="code-cell">{{ $r->code }}</td>
                                <td>
                                    @php $poId = \App\Support\DemoData::purchaseOrders()->firstWhere('code', $r->po_code)?->id; @endphp
                                    @if ($poId)
                                        <a href="{{ route('purchases.edit', $poId) }}" class="link-strong code-cell" title="元の発注を見る">{{ $r->po_code }}</a>
                                    @else
                                        <span class="code-cell">{{ $r->po_code }}</span>
                                    @endif
                                </td>
                                <td>{{ $r->supplier }}</td>
                                <td class="code-cell t-strong">{{ $r->sku }}</td>
                                <td class="num mono"><span class="badge badge-green badge--plain">+@include('partials.qty', ['qty' => $r->qty, 'productId' => $r->product_id])</span></td>
                                <td class="mono t-muted">{{ $r->date }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
