@extends('layouts.app')

@section('title', '在庫管理')
@section('breadcrumb', '集計 / 在庫管理')

@section('content')
    <div class="page-header">
        <div>
            <h1>在庫管理</h1>
            <p class="lead">品番ごとの現在庫と入出庫の履歴を確認します。</p>
        </div>
        <button class="btn btn-secondary" disabled>@include('partials.icon', ['name' => 'download']) 在庫一覧CSV</button>
    </div>

    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi__icon tone-blue">@include('partials.icon', ['name' => 'archive'])</div>
            <div class="kpi__label">総在庫数</div>
            <div class="kpi__value" style="font-size:22px;">@include('partials.qty', ['qty' => $totalStock])</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-indigo">@include('partials.icon', ['name' => 'yen'])</div>
            <div class="kpi__label">在庫金額（製造コストベース）</div>
            <div class="kpi__value">{{ number_format($stockValue) }}<span style="font-size:14px;font-weight:600;"> 円</span></div>
            <div class="kpi__sub">製造コスト単価 × 現在庫で評価</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-rose">@include('partials.icon', ['name' => 'alert'])</div>
            <div class="kpi__label">在庫不足品番</div>
            <div class="kpi__value">{{ $lowStockCount }}<span style="font-size:14px;font-weight:600;"> 件</span></div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head"><h2 class="card__title">検索</h2></div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'code' => ['label' => '発注番号', 'placeholder' => 'PO-2606-001'],
                'customer' => ['label' => '得意先'],
                'supplier' => ['label' => '仕入先'],
                'sku' => ['label' => '品番'],
                'due' => ['label' => '日付'],
                'status' => [
                    'label' => '状態',
                    'options' => array_merge(
                        ['在庫不足' => '在庫不足', '残少なめ' => '残少なめ', '十分' => '十分'],
                        collect(\App\Support\DemoData::PO_STAGES)->mapWithKeys(fn ($s) => [$s => $s])->all()
                    ),
                ],
            ],
        ])
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card__head"><h2 class="card__title">現在庫一覧</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>品番</th><th class="num">現在庫</th><th class="num">安全在庫</th><th>状態</th><th style="width:88px;"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $p)
                                <tr>
                                    <td class="code-cell">
                                        <a href="{{ route('inventory.show', $p->id) }}" class="link-strong">{{ $p->sku }}</a>
                                    </td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $p->stock, 'productId' => $p->id])</td>
                                    <td class="num mono t-muted">@include('partials.qty', ['qty' => $p->stock_min, 'productId' => $p->id])</td>
                                    <td>
                                        @if ($p->stock < $p->stock_min)
                                            <span class="badge badge-rose">在庫不足</span>
                                        @elseif ($p->stock < $p->stock_min * 1.5)
                                            <span class="badge badge-amber">残少なめ</span>
                                        @else
                                            <span class="badge badge-green">十分</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('inventory.show', $p->id) }}" class="btn btn-secondary btn-sm">詳細</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">入出庫履歴</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>日付</th><th>品番</th><th>区分</th><th class="num">数量</th><th>備考</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($movements as $m)
                                <tr>
                                    <td class="mono t-muted">{{ $m->date }}</td>
                                    <td class="code-cell t-strong">{{ $m->sku }}</td>
                                    <td>
                                        @if ($m->type === '入庫')
                                            <span class="badge badge-green badge--plain">入庫</span>
                                        @else
                                            <span class="badge badge-rose badge--plain">出庫</span>
                                        @endif
                                    </td>
                                    <td class="num mono">{{ $m->type === '入庫' ? '+' : '-' }}@include('partials.qty', ['qty' => $m->qty, 'productId' => $m->product_id])</td>
                                    <td class="t-muted" style="font-size:12px;">{{ $m->note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card__head">
            <h2 class="card__title">生産中（発注済み）</h2>
            <span class="badge badge-amber badge--plain">{{ $inProduction->count() }} 件</span>
        </div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>品番</th><th class="num">数量</th><th>進捗段階</th><th>上がり予定</th><th>発注番号</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($inProduction as $po)
                            <tr>
                                <td class="code-cell t-strong">{{ $po->sku }}</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $po->qty, 'productId' => $po->product_id])</td>
                                <td><span class="badge badge-indigo badge--plain">{{ $po->stage }}</span></td>
                                <td class="mono">{{ $po->finish_date }}</td>
                                <td><a href="{{ route('purchases.edit', $po->id) }}" class="link-strong code-cell">{{ $po->code }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">生産中の発注はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
