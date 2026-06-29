@extends('layouts.app')

@section('title', '在庫詳細')
@section('breadcrumb', '集計 / 在庫管理 / 詳細')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="code-cell" style="font-size:20px;">{{ $product->sku }}</h1>
            <p class="lead">カラー：{{ $product->color }} ／ この品番の現在庫と、受注に対する過不足を確認します。</p>
        </div>
        <a href="{{ route('inventory.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 在庫一覧に戻る
        </a>
    </div>

    @include('partials.cost-warning', ['warnings' => $costWarnings])

    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi__icon tone-blue">@include('partials.icon', ['name' => 'archive'])</div>
            <div class="kpi__label">現在庫</div>
            <div class="kpi__value" style="font-size:22px;">@include('partials.qty', ['qty' => $effectiveStock, 'productId' => $product->id])</div>
            <div class="kpi__sub">安全在庫 @include('partials.qty', ['qty' => $product->stock_min, 'productId' => $product->id])</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-amber">@include('partials.icon', ['name' => 'cart'])</div>
            <div class="kpi__label">受注残（未出荷分）</div>
            <div class="kpi__value" style="font-size:22px;">@include('partials.qty', ['qty' => $outstanding, 'productId' => $product->id])</div>
            <div class="kpi__sub">出荷が必要な合計数量</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon {{ $balance < 0 ? 'tone-rose' : 'tone-green' }}">@include('partials.icon', ['name' => $balance < 0 ? 'alert' : 'check'])</div>
            <div class="kpi__label">過不足（現在庫 − 受注残）</div>
            <div class="kpi__value" style="color:{{ $balance < 0 ? 'var(--danger)' : 'var(--success)' }};">
                {{ $balance >= 0 ? '+' : '' }}@include('partials.qty', ['qty' => abs($balance), 'productId' => $product->id])
            </div>
            <div class="kpi__sub">{{ $balance < 0 ? '在庫が不足しています' : '受注を満たせます' }}</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-indigo">@include('partials.icon', ['name' => 'yen'])</div>
            <div class="kpi__label">在庫金額（コストベース）</div>
            <div class="kpi__value">
                @if ($costCalculable)
                    {{ number_format($unitCost * $effectiveStock) }}<span style="font-size:14px;font-weight:600;"> 円</span>
                @else
                    <span class="t-muted">算出不可</span>
                @endif
            </div>
            <div class="kpi__sub">
                @if ($costCalculable)
                    製造コスト単価 {{ number_format($unitCost) }} 円/m
                @else
                    対象月の糸単価が未登録です
                @endif
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <h2 class="card__title">現在庫と受注残の比較</h2>
            @if ($balance < 0)
                <span class="badge badge-rose">@include('partials.qty', ['qty' => abs($balance), 'productId' => $product->id]) 不足</span>
            @else
                <span class="badge badge-green">充足（余り @include('partials.qty', ['qty' => $balance, 'productId' => $product->id])）</span>
            @endif
        </div>
        <div class="card__body">
            @php
                // 2本のバーを同じ目盛りで描くため、最大値を基準にする。
                $scale = max($effectiveStock, $outstanding, 1);
            @endphp
            <div class="cmp">
                <div class="cmp__row">
                    <div class="cmp__label">現在庫</div>
                    <div class="cmp__track">
                        <div class="cmp__fill cmp__fill--stock" style="width:{{ round($effectiveStock / $scale * 100) }}%;"></div>
                    </div>
                    <div class="cmp__value mono">@include('partials.qty', ['qty' => $effectiveStock, 'productId' => $product->id])</div>
                </div>
                <div class="cmp__row">
                    <div class="cmp__label">受注残</div>
                    <div class="cmp__track">
                        <div class="cmp__fill cmp__fill--demand" style="width:{{ round($outstanding / $scale * 100) }}%;"></div>
                    </div>
                    <div class="cmp__value mono">@include('partials.qty', ['qty' => $outstanding, 'productId' => $product->id])</div>
                </div>
            </div>
            <div class="cmp-legend">
                <span><i class="cmp-legend__dot cmp-legend__dot--stock"></i>現在庫</span>
                <span><i class="cmp-legend__dot cmp-legend__dot--demand"></i>受注残（未出荷）</span>
            </div>
        </div>
    </div>

    <div class="card" id="allocation" style="margin-bottom:16px;">
        <div class="card__head">
            <div>
                <h2 class="card__title">在庫引当</h2>
                <div class="field-hint">現在庫引当（入荷済み発注）と発注引当（未入荷残）を分けて、受注へ配分します。</div>
            </div>
            @if ($allocationShortage > 0)
                <span class="badge badge-rose">@include('partials.qty', ['qty' => $allocationShortage, 'productId' => $product->id]) 未引当</span>
            @else
                <span class="badge badge-green">全受注を引当</span>
            @endif
        </div>
        <div class="card__body">
            <div class="allocation-summary">
                <div class="allocation-summary__item">
                    <div class="allocation-summary__label">現在庫引当</div>
                    <div class="allocation-summary__value mono">@include('partials.qty', ['qty' => $stockAllocatedTotal, 'productId' => $product->id])</div>
                </div>
                <div class="allocation-summary__item">
                    <div class="allocation-summary__label">発注引当</div>
                    <div class="allocation-summary__value mono">@include('partials.qty', ['qty' => $poAllocatedTotal, 'productId' => $product->id])</div>
                </div>
                <div class="allocation-summary__item">
                    <div class="allocation-summary__label">未割当在庫</div>
                    <div class="allocation-summary__value mono">@include('partials.qty', ['qty' => $unallocatedStock, 'productId' => $product->id])</div>
                </div>
                <div class="allocation-summary__item">
                    <div class="allocation-summary__label">未引当の受注残</div>
                    <div class="allocation-summary__value mono {{ $allocationShortage > 0 ? 'text-danger' : 'text-success' }}">
                        @include('partials.qty', ['qty' => $allocationShortage, 'productId' => $product->id])
                    </div>
                </div>
            </div>

            @if ($purchases->isNotEmpty())
                <div class="po-usage-summary" style="margin-bottom:16px;">
                    @foreach ($purchases as $po)
                        @php
                            $stockUsed = $stockUsageByPo[$po->id] ?? 0;
                            $poUsed = $poUsageByPo[$po->id] ?? 0;
                            $received = \App\Support\DemoState::effectiveReceived($po->id);
                            $poRem = \App\Support\DemoState::poRemaining($po->id);
                        @endphp
                        <div class="po-usage-item">
                            <div class="po-usage-item__code">
                                <a href="{{ route('purchases.show', $po->id) }}" class="link-strong">{{ $po->code }}</a>
                            </div>
                            <div class="po-usage-item__nums mono" style="font-size:12px;">
                                <span class="t-muted">入荷済 @include('partials.qty', ['qty' => $received, 'productId' => $product->id])</span>
                                <span class="{{ $stockUsed > 0 ? 't-strong' : 't-muted' }}">在庫引当 @include('partials.qty', ['qty' => $stockUsed, 'productId' => $product->id])</span>
                                <span class="{{ $poUsed > 0 ? 't-strong' : 't-muted' }}">発注引当 @include('partials.qty', ['qty' => $poUsed, 'productId' => $product->id])</span>
                                <span class="t-muted">残 @include('partials.qty', ['qty' => $poRem, 'productId' => $product->id])</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('inventory.allocate', $product->id) }}" method="POST" id="allocation-form">
                @csrf
                @include('partials.qty-unit-toggle', ['pageKey' => 'allocation'])
                <script id="alloc-meta" type="application/json">
                    {!! json_encode(['stock' => $effectiveStock, 'currentOrderId' => 0, 'metersPerTan' => $product->meters_per_tan ?? 50], JSON_UNESCAPED_UNICODE) !!}
                </script>
                <script id="stock-po-options" type="application/json">{!! json_encode($stockPoOptions->values(), JSON_UNESCAPED_UNICODE) !!}</script>
                <script id="po-po-options" type="application/json">{!! json_encode($poPoOptions->values(), JSON_UNESCAPED_UNICODE) !!}</script>

                @if ($allocationOrders->isEmpty())
                    <p class="t-muted" style="text-align:center;padding:24px 0;">割り当てが必要な受注残はありません。</p>
                @else
                    <div class="table-wrap allocation-table-wrap" style="margin-bottom:16px;">
                        <table class="data allocation-table">
                            <colgroup>
                                <col class="col-order">
                                <col class="col-customer">
                                <col class="col-due">
                                <col class="col-remaining">
                                <col class="col-stock">
                                <col class="col-po">
                                <col class="col-progress">
                                <col class="col-status">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="col-order">割当先の受注</th>
                                    <th class="col-customer">得意先</th>
                                    <th class="col-due">納期</th>
                                    <th class="col-remaining num">受注残</th>
                                    <th class="col-stock">現在庫引当<span class="th-sub">入荷済み発注</span></th>
                                    <th class="col-po">発注引当<span class="th-sub">未入荷残</span></th>
                                    <th class="col-progress">引当進捗</th>
                                    <th class="col-status">状況</th>
                                </tr>
                            </thead>
                            <tbody>
                                @include('partials.allocation-rows', [
                                    'orders' => $allocationOrders,
                                    'productId' => $product->id,
                                    'stockPoOptions' => $stockPoOptions,
                                    'poPoOptions' => $poPoOptions,
                                    'highlightOrderId' => null,
                                    'allocMetersPerTan' => $product->meters_per_tan ?? 50,
                                ])
                            </tbody>
                        </table>
                    </div>
                    <div class="allocation-actions">
                        <button type="submit" class="btn btn-primary" id="alloc-submit-btn">@include('partials.icon', ['name' => 'check']) 引当を保存する</button>
                    </div>
                @endif
            </form>

            @if (!empty($conversionHistory))
                <div style="margin-top:16px;padding:12px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;">
                    <div style="font-size:13px;font-weight:600;margin-bottom:8px;">入荷による引当変換履歴</div>
                    <div class="table-wrap">
                        <table class="data">
                            <thead><tr><th>日時</th><th>入荷</th><th>受注</th><th>発注</th><th class="num">数量</th></tr></thead>
                            <tbody>
                                @foreach ($conversionHistory as $ev)
                                    @php
                                        $evOrder = \App\Support\DemoData::orders()->firstWhere('id', $ev['order_id'] ?? 0);
                                        $evPo = \App\Support\DemoData::purchaseOrders()->firstWhere('id', $ev['po_id'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td class="mono" style="font-size:12px;">{{ $ev['at'] ?? '' }}</td>
                                        <td>{{ $ev['receiving_code'] ?? '' }}</td>
                                        <td>{{ $evOrder?->code ?? '' }}</td>
                                        <td>{{ $evPo?->code ?? '' }}</td>
                                        <td class="num mono">@include('partials.qty', ['qty' => $ev['qty'] ?? 0, 'productId' => $product->id])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('partials.allocation-scripts')

    <div class="grid grid-equal-2" style="margin-bottom:16px;">
        <div class="card">
            <div class="card__head"><h2 class="card__title">受注履歴（{{ $orders->count() }} 件）</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>受注番号</th><th>得意先</th><th class="num">数量</th>
                                <th class="num">出荷済</th><th class="num">受注残</th>
                                <th>納期</th><th>ステータス</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $o)
                                <tr>
                                    <td class="code-cell">
                                        <a href="{{ route('orders.edit', $o->id) }}" class="link-strong">{{ $o->code }}</a>
                                    </td>
                                    <td>{{ $o->customer }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $o->qty, 'productId' => $product->id])</td>
                                    <td class="num mono t-muted">@include('partials.qty', ['qty' => $o->shipped, 'productId' => $product->id])</td>
                                    <td class="num mono {{ $o->remaining > 0 ? 't-strong' : 't-muted' }}">@include('partials.qty', ['qty' => $o->remaining, 'productId' => $product->id])</td>
                                    <td class="mono">{{ $o->due_date }}</td>
                                    <td>@include('partials.status', ['status' => $o->status])</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty">受注履歴はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">発注履歴（{{ $purchases->count() }} 件）</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>発注番号</th><th>得意先</th><th class="num">数量</th>
                                <th>進捗段階</th><th>入荷予定</th><th>上がり予定</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchases as $po)
                                <tr>
                                    <td class="code-cell">
                                        <a href="{{ route('purchases.show', $po->id) }}" class="link-strong">{{ $po->code }}</a>
                                    </td>
                                    <td>{{ $po->customer }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $po->qty, 'productId' => $product->id])</td>
                                    <td><span class="badge badge-indigo badge--plain">{{ $po->stage }}</span></td>
                                    <td class="mono">{{ $po->eta }}</td>
                                    <td class="mono">{{ $po->finish_date }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="empty">発注履歴はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__head"><h2 class="card__title">入出庫履歴</h2></div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>日付</th><th>区分</th><th class="num">数量</th><th>備考</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $m)
                            <tr>
                                <td class="mono t-muted">{{ $m->date }}</td>
                                <td>
                                    @if ($m->type === '入庫')
                                        <span class="badge badge-green badge--plain">入庫</span>
                                    @else
                                        <span class="badge badge-rose badge--plain">出庫</span>
                                    @endif
                                </td>
                                <td class="num mono">{{ $m->type === '入庫' ? '+' : '-' }}@include('partials.qty', ['qty' => $m->qty, 'productId' => $product->id])</td>
                                <td class="t-muted" style="font-size:12px;">{{ $m->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">入出庫の履歴はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
