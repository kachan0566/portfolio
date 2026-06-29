@extends('layouts.app')

@section('title', '在庫管理')
@section('breadcrumb', '集計 / 在庫管理')

@section('content')
    <div class="page-header">
        <div>
            <h1>在庫管理</h1>
            <p class="lead">製品・生機（染工場）・糸の在庫と入出庫履歴を確認します。</p>
        </div>
        <button class="btn btn-secondary" disabled>@include('partials.icon', ['name' => 'download']) 在庫一覧CSV</button>
    </div>

    @include('partials.cost-warning', ['warnings' => $costWarnings])

    @if ($tab === 'product')
    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi__icon tone-blue">@include('partials.icon', ['name' => 'archive'])</div>
            <div class="kpi__label">総在庫数</div>
            <div class="kpi__value" style="font-size:22px;">@include('partials.qty', ['qty' => $totalStock])</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-indigo">@include('partials.icon', ['name' => 'yen'])</div>
            <div class="kpi__label">在庫金額（製造コストベース）</div>
            <div class="kpi__value">
                {{ number_format($stockValue) }}<span style="font-size:14px;font-weight:600;"> 円</span>
            </div>
            <div class="kpi__sub">
                製造コスト単価 × 現在庫で評価
                @if ($hasUncalculableCost)
                    <span class="t-muted">（算出可能な品番のみ合算）</span>
                @endif
            </div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-rose">@include('partials.icon', ['name' => 'alert'])</div>
            <div class="kpi__label">在庫不足品番</div>
            <div class="kpi__value">{{ $lowStockCount }}<span style="font-size:14px;font-weight:600;"> 件</span></div>
        </div>
    </div>
    @endif

    @if (in_array($tab, ['product', 'greige', 'yarn'], true))
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
    @endif

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head" style="padding-bottom:0;border-bottom:none;">
            <div style="display:flex;gap:4px;border-bottom:1px solid var(--border);width:100%;padding-bottom:0;flex-wrap:wrap;">
                <a href="{{ route('inventory.index', array_merge($search, ['tab' => 'product'])) }}"
                   class="btn btn-ghost btn-sm"
                   style="border-radius:6px 6px 0 0;margin-bottom:-1px;{{ $tab === 'product' ? 'border:1px solid var(--border);border-bottom-color:var(--surface);background:var(--surface);font-weight:600;' : 'border:1px solid transparent;' }}">
                    製品在庫
                </a>
                <a href="{{ route('inventory.index', array_merge($search, ['tab' => 'forecast', 'ym' => $forecastYm])) }}"
                   class="btn btn-ghost btn-sm"
                   style="border-radius:6px 6px 0 0;margin-bottom:-1px;{{ $tab === 'forecast' ? 'border:1px solid var(--border);border-bottom-color:var(--surface);background:var(--surface);font-weight:600;' : 'border:1px solid transparent;' }}">
                    月末在庫予想
                </a>
                <a href="{{ route('inventory.index', array_merge($search, ['tab' => 'long_term'])) }}"
                   class="btn btn-ghost btn-sm"
                   style="border-radius:6px 6px 0 0;margin-bottom:-1px;{{ $tab === 'long_term' ? 'border:1px solid var(--border);border-bottom-color:var(--surface);background:var(--surface);font-weight:600;' : 'border:1px solid transparent;' }}">
                    長期在庫
                </a>
                <a href="{{ route('inventory.index', array_merge($search, ['tab' => 'greige'])) }}"
                   class="btn btn-ghost btn-sm"
                   style="border-radius:6px 6px 0 0;margin-bottom:-1px;{{ $tab === 'greige' ? 'border:1px solid var(--border);border-bottom-color:var(--surface);background:var(--surface);font-weight:600;' : 'border:1px solid transparent;' }}">
                    生機在庫（染工場）
                    @if ($greigeEntries->isNotEmpty())
                        <span class="badge badge-amber badge--plain" style="margin-left:4px;">{{ $greigeEntries->count() }}</span>
                    @endif
                </a>
                <a href="{{ route('inventory.index', array_merge($search, ['tab' => 'yarn'])) }}"
                   class="btn btn-ghost btn-sm"
                   style="border-radius:6px 6px 0 0;margin-bottom:-1px;{{ $tab === 'yarn' ? 'border:1px solid var(--border);border-bottom-color:var(--surface);background:var(--surface);font-weight:600;' : 'border:1px solid transparent;' }}">
                    糸在庫
                </a>
            </div>
        </div>
    </div>

    @if ($tab === 'forecast')
        @include('inventory.partials.forecast-tab')
    @elseif ($tab === 'long_term')
        @include('inventory.partials.long-term-tab')
    @elseif ($tab === 'yarn')
        <div class="kpi-grid" style="margin-bottom:16px;">
            <div class="kpi">
                <div class="kpi__icon tone-blue">@include('partials.icon', ['name' => 'archive'])</div>
                <div class="kpi__label">糸在庫合計</div>
                <div class="kpi__value" style="font-size:22px;">{{ number_format($yarnTotalKg, 2) }}<span style="font-size:14px;font-weight:600;"> kg</span></div>
                <div class="kpi__sub">{{ $yarnRows->count() }} 品種</div>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="card">
                <div class="card__head"><h2 class="card__title">糸在庫一覧</h2></div>
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>糸品番</th>
                                    <th class="num">現在庫</th>
                                    <th class="num">発注残</th>
                                    <th class="num">引当</th>
                                    <th class="num">利用可能</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($yarnRows as $y)
                                    <tr>
                                        <td>
                                            <span class="code-cell t-strong">{{ $y->sku }}</span>
                                            <div class="t-muted" style="font-size:11px;">{{ $y->name }}</div>
                                        </td>
                                        <td class="num mono">{{ number_format($y->stock_kg, 2) }} kg</td>
                                        <td class="num mono t-muted">{{ number_format($y->on_order_kg, 2) }} kg</td>
                                        <td class="num mono t-muted">{{ number_format($y->allocated_kg, 2) }} kg</td>
                                        <td class="num mono">{{ number_format($y->available_kg, 2) }} kg</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card__head"><h2 class="card__title">糸入荷履歴</h2></div>
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr><th>日付</th><th>糸品番</th><th class="num">数量</th><th>備考</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($yarnMovements as $m)
                                    <tr>
                                        <td class="mono t-muted">{{ $m->date }}</td>
                                        <td class="code-cell t-strong">{{ $m->sku }}</td>
                                        <td class="num mono"><span class="badge badge-green badge--plain">+{{ number_format($m->qty_kg, 2) }} kg</span></td>
                                        <td class="t-muted" style="font-size:12px;">{{ $m->note }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="empty">糸の入荷履歴はありません。</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($tab === 'greige')
        <div class="kpi-grid" style="margin-bottom:16px;">
            <div class="kpi">
                <div class="kpi__icon tone-amber">@include('partials.icon', ['name' => 'archive'])</div>
                <div class="kpi__label">染工場 生機在庫合計</div>
                <div class="kpi__value" style="font-size:22px;">
                    @if ($greigeTotalMeters > 0)
                        {{ \App\Support\QtyHelper::formatTanCount($greigeTotalTan) }}反 / {{ number_format($greigeTotalMeters) }}m
                    @else
                        0反 / 0m
                    @endif
                </div>
                <div class="kpi__sub">{{ $greigeEntries->count() }} ロット</div>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">生機在庫一覧（生機発注入荷）</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>発注番号</th>
                                <th>生機品番</th>
                                <th class="num">入荷済数量</th>
                                <th>出荷先</th>
                                <th>納期</th>
                                <th style="width:88px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($greigeEntries as $g)
                                <tr>
                                    <td class="code-cell"><a href="{{ route('purchases.show', $g->po_id) }}" class="link-strong">{{ $g->po_code }}</a></td>
                                    <td class="code-cell t-strong">{{ $g->greige_sku }}<div class="t-muted" style="font-size:11px;">{{ $g->greige_name }}</div></td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $g->qty_meters, 'isGreige' => true, 'greigeSku' => $g->greige_sku])</td>
                                    <td>{{ $g->ship_to }}</td>
                                    <td class="mono">{{ $g->due_date }}</td>
                                    <td><a href="{{ route('purchases.show', $g->po_id) }}" class="btn btn-secondary btn-sm">詳細</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty">染工場に生機在庫はありません。生機発注の入荷登録を行うとここに表示されます。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
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
                                <td><a href="{{ route('purchases.show', $po->id) }}" class="link-strong code-cell">{{ $po->code }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">生産中の発注はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
@endsection
