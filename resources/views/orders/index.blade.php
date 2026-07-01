@extends('layouts.app')

@section('title', '受注管理')
@section('breadcrumb', '取引 / 受注管理')

@section('content')
    <div class="page-header">
        <div>
            <h1>受注管理</h1>
            <p class="lead">得意先との「約束（いつまでに・何を・いくつ納めるか）」を登録する画面です。実際にモノを送った記録は「出荷処理」で行い、1件の受注を複数回に分けて出荷（分納）することもできます。</p>
        </div>
        <a href="{{ route('orders.create') }}" class="btn btn-primary">
            @include('partials.icon', ['name' => 'plus']) 受注を登録
        </a>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">受注一覧（{{ $orders->count() }} 件）</h2>
            <div class="legend">
                <span>未出荷 {{ $orders->where('status', '未出荷')->count() }}</span>
                <span>一部出荷 {{ $orders->where('status', '一部出荷')->count() }}</span>
                <span>出荷済み {{ $orders->where('status', '出荷済み')->count() }}</span>
            </div>
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'code' => ['label' => '受注番号', 'placeholder' => 'SO-2606-001'],
                'customer' => ['label' => '得意先'],
                'sku' => ['label' => '品番'],
                'due' => ['label' => '納期'],
                'status' => [
                    'label' => 'ステータス',
                    'options' => [
                        '出荷残あり' => '出荷残あり',
                        '未出荷' => '未出荷',
                        '一部出荷' => '一部出荷',
                        '出荷済み' => '出荷済み',
                    ],
                ],
            ],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>受注番号</th>
                            <th>得意先</th>
                            <th>品番</th>
                            <th class="num">数量</th>
                            <th class="num">販売単価</th>
                            <th>進捗</th>
                            <th>引当状況</th>
                            <th>納期</th>
                            <th>出荷予定日メモ</th>
                            <th>ステータス</th>
                            <th style="width:110px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $o)
                            @php $rate = $o->qty > 0 ? round($o->shipped / $o->qty * 100) : 0; @endphp
                            <tr>
                                <td class="code-cell">
                                    <a href="{{ route('orders.show', $o->id) }}" class="link-strong">{{ $o->code }}</a>
                                    @if ($o->is_new_today ?? false)
                                        <span class="badge badge-indigo badge--plain" style="margin-left:6px;">本日受付</span>
                                    @endif
                                </td>
                                <td>{{ $o->customer }}</td>
                                <td>
                                    <a href="{{ route('inventory.show', $o->product_id) }}" class="link-strong code-cell" title="この品番の在庫を見る">{{ $o->sku }}</a>
                                    <div class="t-muted" style="font-size:11.5px;">{{ $o->color }}</div>
                                </td>
                                <td class="num mono">@include('partials.qty', ['qty' => $o->qty, 'productId' => $o->product_id])</td>
                                <td class="num mono">¥{{ number_format($o->price) }}</td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div class="progress">
                                            <div class="progress__bar {{ $rate >= 100 ? 'full' : ($rate === 0 ? 'none' : '') }}" style="width:{{ $rate }}%;"></div>
                                        </div>
                                        <span class="mono t-muted" style="font-size:11.5px;">
                                            @include('partials.qty', ['qty' => $o->shipped, 'productId' => $o->product_id]) /
                                            @include('partials.qty', ['qty' => $o->qty, 'productId' => $o->product_id])
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($o->allocation_status)
                                        <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                            <a href="{{ route('inventory.show', $o->product_id) }}#allocation" class="badge {{ $o->allocation_badge }}" style="text-decoration:none;">{{ $o->allocation_status }}</a>
                                            @if ($o->shippable_status)
                                                <span class="badge {{ $o->shippable_badge }}" style="font-size:11px;">{{ $o->shippable_status }}</span>
                                            @endif
                                        </div>
                                        <div class="mono t-muted" style="font-size:11.5px;margin-top:4px;">
                                            在庫 {{ $o->stock_allocated }}m + 発注 {{ $o->po_allocated }}m
                                            / {{ $o->remaining }}m
                                        </div>
                                    @else
                                        <span class="t-muted">—</span>
                                    @endif
                                </td>
                                <td class="mono">{{ $o->due_date }}</td>
                                <td class="t-muted" style="font-size:12px;max-width:200px;">{{ $o->ship_memo }}</td>
                                <td>@include('partials.status', ['status' => $o->status])</td>
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('orders.edit', $o->id) }}" class="btn btn-secondary btn-sm">編集</a>
                                        <form action="{{ route('orders.destroy', $o->id) }}" method="POST" class="inline-form" onsubmit="return confirm('{{ $o->code }} を削除しますか？')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">削除</button>
                                        </form>
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
