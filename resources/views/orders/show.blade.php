@extends('layouts.app')

@section('title', '受注詳細')
@section('breadcrumb', '取引 / 受注管理 / 詳細')

@section('content')
    {{-- ───── ページヘッダー ───── --}}
    <div class="page-header">
        <div>
            <h1 class="code-cell" style="font-size:20px;">{{ $order->code }}</h1>
            <p class="lead">{{ $order->customer }} ／ {{ $order->sku }}（{{ $order->color }}）／ 納期 {{ $order->due_date }}</p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('orders.edit', $order->id) }}" class="btn btn-secondary">受注を編集</a>
            <a href="{{ route('orders.index') }}" class="btn btn-secondary">
                @include('partials.icon', ['name' => 'back']) 受注一覧に戻る
            </a>
        </div>
    </div>

    {{-- フラッシュメッセージ --}}
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">
            @include('partials.icon', ['name' => 'check'])
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom:16px;">
            @include('partials.icon', ['name' => 'alert'])
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if (session('just_created'))
        <div class="alert alert-success" style="margin-bottom:16px;">
            @include('partials.icon', ['name' => 'check'])
            <strong>受注を登録しました。</strong> 下の引当パネルで在庫を割り当ててください。
        </div>
    @endif

    {{-- ───── KPI グリッド ───── --}}
    <div class="kpi-grid" style="margin-bottom:16px;">
        <div class="kpi">
            <div class="kpi__icon tone-blue">@include('partials.icon', ['name' => 'cart'])</div>
            <div class="kpi__label">受注数量</div>
            <div class="kpi__value" style="font-size:22px;">@include('partials.qty', ['qty' => $order->qty, 'productId' => $order->product_id])</div>
            <div class="kpi__sub">受注日 {{ $order->order_date }}</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon tone-green">@include('partials.icon', ['name' => 'check'])</div>
            <div class="kpi__label">出荷済</div>
            <div class="kpi__value" style="font-size:22px;">@include('partials.qty', ['qty' => $order->shipped, 'productId' => $order->product_id])</div>
            <div class="kpi__sub">{{ $shipRate }}% 完了</div>
        </div>
        <div class="kpi">
            <div class="kpi__icon {{ $order->remaining > 0 ? 'tone-amber' : 'tone-green' }}">@include('partials.icon', ['name' => 'archive'])</div>
            <div class="kpi__label">受注残（出荷待ち）</div>
            <div class="kpi__value" style="font-size:22px;">@include('partials.qty', ['qty' => $order->remaining, 'productId' => $order->product_id])</div>
            <div class="kpi__sub">{{ $order->remaining > 0 ? '残り '.(100 - $shipRate).'%' : '出荷完了' }}</div>
        </div>
        <div class="kpi">
            @php
                $allocationIconTone = match ($order->allocation_status) {
                    '引当完了' => 'tone-green',
                    '一部引当' => 'tone-amber',
                    '未引当'   => 'tone-rose',
                    default    => 'tone-blue',
                };
            @endphp
            <div class="kpi__icon {{ $allocationIconTone }}">@include('partials.icon', ['name' => 'archive'])</div>
            <div class="kpi__label">引当状況</div>
            @if ($order->remaining > 0 && $order->allocation_status)
                <div class="kpi__value" style="font-size:18px;display:flex;flex-wrap:wrap;gap:6px;">
                    <span class="badge {{ $order->allocation_badge }}">{{ $order->allocation_status }}</span>
                    @if ($order->shippable_status)
                        <span class="badge {{ $order->shippable_badge }}">{{ $order->shippable_status }}</span>
                    @endif
                </div>
                <div class="kpi__sub">
                    在庫 @include('partials.qty', ['qty' => $order->stock_allocated, 'productId' => $order->product_id])
                    + 発注 @include('partials.qty', ['qty' => $order->po_allocated, 'productId' => $order->product_id])
                    / @include('partials.qty', ['qty' => $order->remaining, 'productId' => $order->product_id]) 残
                </div>
            @else
                <div class="kpi__value" style="font-size:18px;">@include('partials.status', ['status' => $order->status])</div>
                <div class="kpi__sub">出荷完了のため引当不要</div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         メインパネル：引当の管理
         同品番の受注間で在庫を自由に配分できる
    ════════════════════════════════════════════════════════ --}}
    @if ($order->remaining > 0)
        <div class="card" style="margin-bottom:16px;">
            <div class="card__head" style="align-items:flex-start;">
                <div>
                    <h2 class="card__title">引当の管理</h2>
                    <p class="field-hint" style="margin:4px 0 0;">
                        <strong>{{ $order->sku }}（{{ $order->color }}）</strong> の現在庫 <strong>@include('partials.qty', ['qty' => $effectiveStock, 'productId' => $product->id])</strong> を、入荷済み発注から「現在庫引当」として配分できます。未入荷の発注残は「発注引当」として別管理します。
                    </p>
                </div>
                <div style="display:flex;align-items:center;gap:8px;white-space:nowrap;flex-wrap:wrap;">
                    @if ($effectiveStock === 0)
                        <span class="badge badge-rose">在庫なし</span>
                    @elseif ($order->stock_allocated >= $order->remaining)
                        <span class="badge badge-green">現在庫確保完了</span>
                    @else
                        <span class="badge badge-amber">在庫 @include('partials.qty', ['qty' => $effectiveStock, 'productId' => $product->id])</span>
                    @endif
                </div>
            </div>

            <div class="card__body">

                @php
                    $stockTotal = $effectiveStock;
                    $otherPct   = $stockTotal > 0 ? round($otherOrdersStockAllocated / $stockTotal * 100) : 0;
                    $thisPct    = $stockTotal > 0 ? round($order->stock_allocated / $stockTotal * 100) : 0;
                    $freePct    = max(0, 100 - $otherPct - $thisPct);
                    $freeStock  = max(0, $stockTotal - $otherOrdersStockAllocated - $order->stock_allocated);
                @endphp
                <div style="margin-bottom:20px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-faint);margin-bottom:6px;">
                        <span>現在庫引当の使用状況（発注引当は含まない）</span>
                        <span class="mono">現在庫 @include('partials.qty', ['qty' => $stockTotal, 'productId' => $product->id])</span>
                    </div>
                    <div style="width:100%;height:14px;background:#e5e7eb;border-radius:7px;overflow:hidden;display:flex;" id="budget-bar-track">
                        <div id="budget-bar-others" style="height:100%;background:#f59e0b;width:{{ $otherPct }}%;transition:width 0.2s;" title="他の受注への引当"></div>
                        <div id="budget-bar-this" style="height:100%;background:#3b82f6;width:{{ $thisPct }}%;transition:width 0.2s;" title="この受注への引当"></div>
                        <div id="budget-bar-free" style="height:100%;background:#d1fae5;width:{{ $freePct }}%;transition:width 0.2s;" title="未配分"></div>
                    </div>
                    <div style="display:flex;gap:20px;font-size:12px;margin-top:8px;">
                        <span><span style="display:inline-block;width:10px;height:10px;background:#f59e0b;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>他の受注 <strong class="mono" id="budget-other-text">@include('partials.qty', ['qty' => $otherOrdersStockAllocated, 'productId' => $product->id])</strong></span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:#3b82f6;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>この受注 <strong class="mono" id="budget-this-text">@include('partials.qty', ['qty' => $order->stock_allocated, 'productId' => $product->id])</strong></span>
                        <span style="color:var(--text-faint);"><span style="display:inline-block;width:10px;height:10px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>未配分 <strong class="mono" id="budget-free-text">@include('partials.qty', ['qty' => $freeStock, 'productId' => $product->id])</strong></span>
                        <span id="budget-over-warning" style="color:#ef4444;font-weight:600;display:none;">@include('partials.icon', ['name' => 'alert']) 在庫超過！</span>
                    </div>
                </div>

                <form action="{{ route('orders.save-allocation', $order->id) }}" method="POST" id="allocation-form">
                    @csrf
                    <script id="alloc-meta" type="application/json">
                        {!! json_encode([
                            'stock'          => $stockTotal,
                            'currentOrderId' => $order->id,
                            'metersPerTan'   => $product->meters_per_tan ?? 50,
                        ], JSON_UNESCAPED_UNICODE) !!}
                    </script>
                    <script id="stock-po-options" type="application/json">
                        {!! json_encode($stockPoOptions->values(), JSON_UNESCAPED_UNICODE) !!}
                    </script>
                    <script id="po-po-options" type="application/json">
                        {!! json_encode($poPoOptions->values(), JSON_UNESCAPED_UNICODE) !!}
                    </script>

                    @if ($sameProductOrders->isEmpty())
                        <p class="t-muted" style="text-align:center;padding:24px 0;">同品番で受注残のある受注はありません。</p>
                    @elseif ($stockPoOptions->isEmpty() && $poPoOptions->isEmpty())
                        <div class="alert alert-warning" style="margin-bottom:16px;">
                            @include('partials.icon', ['name' => 'alert'])
                            <span>この品番の発注データがないため、割当元を指定できません。先に生産発注を作成してください。</span>
                        </div>
                    @else
                        <div class="table-wrap allocation-table-wrap" style="margin-bottom:16px;">
                            <table class="data allocation-table allocation-table--order-detail">
                                <colgroup>
                                    <col class="col-due">
                                    <col class="col-remaining">
                                    <col class="col-stock">
                                    <col class="col-po">
                                    <col class="col-progress">
                                    <col class="col-status">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th class="col-due">受注 / 納期</th>
                                        <th class="col-remaining num">受注残</th>
                                        <th class="col-stock">現在庫引当<span class="th-sub">入荷済み発注</span></th>
                                        <th class="col-po">発注引当<span class="th-sub">未入荷残</span></th>
                                        <th class="col-progress">引当進捗</th>
                                        <th class="col-status">状況</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @include('partials.allocation-rows', [
                                        'orders' => $sameProductOrders,
                                        'productId' => $product->id,
                                        'stockPoOptions' => $stockPoOptions,
                                        'poPoOptions' => $poPoOptions,
                                        'highlightOrderId' => $order->id,
                                        'mergeOrderInfoInDue' => true,
                                        'allocMetersPerTan' => $product->meters_per_tan ?? 50,
                                    ])
                                </tbody>
                            </table>
                        </div>

                    @endif
                </form>

                @if ($sameProductOrders->isNotEmpty() && ($stockPoOptions->isNotEmpty() || $poPoOptions->isNotEmpty()))
                        @if ($allocationLines->isNotEmpty())
                            <div style="margin-bottom:16px;padding:12px 14px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;">
                                <div style="font-size:13px;font-weight:600;margin-bottom:8px;">この受注への引当明細</div>
                                <div class="table-wrap">
                                    <table class="data">
                                        <thead>
                                            <tr>
                                                <th>区分</th>
                                                <th>来歴（発注）</th>
                                                <th class="num">引当数量</th>
                                                <th style="width:88px;">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($allocationLines as $line)
                                                @php $srcPo = $purchaseOrdersById->get($line->po_id); @endphp
                                                <tr>
                                                    <td>
                                                        <span class="badge {{ $line->type === 'stock' ? 'badge-blue' : 'badge-indigo' }} badge--plain" style="font-size:11px;">
                                                            {{ $line->type === 'stock' ? '現在庫引当' : '発注引当' }}
                                                        </span>
                                                    </td>
                                                    <td class="code-cell">
                                                        @if ($srcPo)
                                                            <a href="{{ route('purchases.edit', $srcPo->id) }}" class="link-strong">{{ $srcPo->code }}</a>
                                                        @else
                                                            <span class="t-muted">発注 #{{ $line->po_id }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="num mono">@include('partials.qty', ['qty' => $line->qty, 'productId' => $order->product_id])</td>
                                                    <td>
                                                        <form action="{{ route('orders.remove-allocation', [$order->id, $line->po_id]) }}?type={{ $line->type }}" method="POST" onsubmit="return confirm('この引当行を解除します。よろしいですか？');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-secondary btn-sm">解除</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if ($conversionHistory)
                            <div style="margin-bottom:16px;padding:12px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;">
                                <div style="font-size:13px;font-weight:600;margin-bottom:8px;">入荷による引当変換履歴</div>
                                <div class="table-wrap">
                                    <table class="data">
                                        <thead>
                                            <tr><th>日時</th><th>入荷</th><th>発注</th><th class="num">数量</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($conversionHistory as $ev)
                                                @php $evPo = $purchaseOrdersById->get($ev['po_id'] ?? 0); @endphp
                                                <tr>
                                                    <td class="mono" style="font-size:12px;">{{ $ev['at'] ?? '' }}</td>
                                                    <td class="code-cell">{{ $ev['receiving_code'] ?? '' }}</td>
                                                    <td class="code-cell">{{ $evPo?->code ?? '#' . ($ev['po_id'] ?? '') }}</td>
                                                    <td class="num mono">@include('partials.qty', ['qty' => $ev['qty'] ?? 0, 'productId' => $product->id])</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div style="display:flex;align-items:center;gap:16px;padding:10px 14px;background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;margin-bottom:16px;font-size:13px;">
                            <span>現在庫 <strong class="mono">@include('partials.qty', ['qty' => $stockTotal, 'productId' => $product->id])</strong></span>
                            <span style="color:var(--text-faint);">|</span>
                            <span>現在庫引当合計 <strong class="mono" id="total-stock-allocated-text">@include('partials.qty', ['qty' => $otherOrdersStockAllocated + $order->stock_allocated, 'productId' => $product->id])</strong></span>
                            <span style="color:var(--text-faint);">|</span>
                            <span>未配分 <strong class="mono" id="total-free-text">@include('partials.qty', ['qty' => $freeStock, 'productId' => $product->id])</strong></span>
                        </div>

                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-bottom:16px;">
                            <button type="submit" class="btn btn-primary" id="alloc-submit-btn" form="allocation-form">
                                @include('partials.icon', ['name' => 'check']) 引当を保存する
                            </button>
                            @if ($order->allocated > 0)
                                <button type="submit" class="btn btn-secondary" form="clear-allocation-form" onclick="return confirm('この受注の引当をすべて解除します。よろしいですか？');">
                                    引当を全解除
                                </button>
                            @endif
                        </div>
                @endif

                @if ($order->allocated > 0)
                    <form id="clear-allocation-form" action="{{ route('orders.clear-allocation', $order->id) }}" method="POST" hidden>
                        @csrf
                    </form>
                @endif

                @if ($supplyShortage > 0)
                    <div class="alert alert-warning" style="margin-top:20px;margin-bottom:12px;align-items:flex-start;">
                        @include('partials.icon', ['name' => 'alert'])
                        <div style="flex:1;">
                            <strong>@include('partials.qty', ['qty' => $supplyShortage, 'productId' => $product->id]) 不足しています。</strong>
                            <p class="field-hint" style="margin:6px 0 0;">
                                未割当在庫 @include('partials.qty', ['qty' => $unallocatedStock, 'productId' => $product->id])
                                と未引当の発注残 @include('partials.qty', ['qty' => $unallocatedPoRemaining, 'productId' => $product->id])
                                を合わせても、受注残 @include('partials.qty', ['qty' => $order->remaining, 'productId' => $product->id]) に足りません。追加の生産発注が必要です。
                            </p>
                        </div>
                    </div>
                @endif
                <div style="margin-top:{{ $supplyShortage > 0 ? '0' : '20px' }};">
                    <a href="{{ route('purchases.create', $supplyShortage > 0 ? ['type' => 'product', 'order_id' => $order->id, 'qty' => $supplyShortage] : ['type' => 'product', 'order_id' => $order->id]) }}" class="btn btn-primary btn-sm">
                        @include('partials.icon', ['name' => 'plus'])
                        @if ($supplyShortage > 0)
                            生産発注を作成（{{ \App\Support\QtyHelper::format($supplyShortage, $product->id) }}）
                        @else
                            生産発注を作成
                        @endif
                    </a>
                </div>

            </div>
        </div>
    @else
        <div class="alert alert-success" style="margin-bottom:16px;">
            @include('partials.icon', ['name' => 'check'])
            <strong>この受注は出荷完了しています。</strong> 追加の引当・出荷登録は不要です。
        </div>
    @endif


    {{-- 出荷準備 --}}
    @if ($order->remaining > 0 && ($order->allocated > 0 || $order->allocation_status === '引当完了'))
        <div class="card" style="margin-bottom:16px;">
            <div class="card__head">
                <div>
                    <h2 class="card__title">出荷準備</h2>
                    @if ($order->shippable)
                        <p class="field-hint" style="margin:4px 0 0;">
                            現在庫引当が受注残をカバーしています。最大 @include('partials.qty', ['qty' => $shippableQty, 'productId' => $order->product_id]) まで出荷確定できます。
                        </p>
                    @elseif ($order->po_allocated > 0)
                        <p class="field-hint" style="margin:4px 0 0;">
                            発注引当 @include('partials.qty', ['qty' => $order->po_allocated, 'productId' => $order->product_id]) は入荷待ちです。出荷確定できるのは現在庫引当 @include('partials.qty', ['qty' => $shippableQty, 'productId' => $order->product_id]) までです。
                        </p>
                    @else
                        <p class="field-hint" style="margin:4px 0 0;">現在庫引当 @include('partials.qty', ['qty' => $shippableQty, 'productId' => $order->product_id]) から出荷できます。</p>
                    @endif
                </div>
                @if ($shippableQty > 0)
                    <a href="{{ route('shipments.create', ['order_id' => $order->id]) }}" class="btn btn-primary">
                        @include('partials.icon', ['name' => 'truck']) 出荷登録へ
                    </a>
                @else
                    <span class="badge badge-amber">入荷待ち（出荷不可）</span>
                @endif
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════
         発注状況
    ════════════════════════════════════════════════════════ --}}
    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <h2 class="card__title">発注状況</h2>
            @if ($order->remaining > 0)
                <a href="{{ route('purchases.create', ['type' => 'product', 'order_id' => $order->id]) }}" class="btn btn-secondary btn-sm">
                    @include('partials.icon', ['name' => 'plus']) 生産発注を作成
                </a>
            @endif
        </div>
        <div class="card__body">

            {{-- この受注に紐づいた発注（生産意図） --}}
            <h3 class="card__subtitle" style="font-size:13px;font-weight:600;margin:0 0 10px;">この受注に紐づいた発注（生産意図）</h3>
            <p class="field-hint" style="margin:0 0 12px;">発注の紐づけ先を変えても、すでに登録した在庫引当の来歴は変わりません。</p>
            @if ($linkedPurchaseOrders->isEmpty())
                <p class="t-muted" style="margin:0 0 20px;font-size:13px;">まだ発注がありません。</p>
            @else
                <div class="table-wrap" style="margin-bottom:20px;">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>発注番号</th>
                                <th>仕入先</th>
                                <th class="num">数量</th>
                                <th>進捗段階</th>
                                <th>入荷予定</th>
                                <th style="width:180px;">紐づけ付け替え</th>
                                <th style="width:72px;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($linkedPurchaseOrders as $po)
                                <tr>
                                    <td class="code-cell">{{ $po->code }}</td>
                                    <td>{{ $po->supplier }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $po->qty, 'productId' => $po->product_id])</td>
                                    <td><span class="badge badge-indigo badge--plain" style="font-size:10.5px;">{{ $po->stage }}</span></td>
                                    <td class="mono">{{ $po->eta }}</td>
                                    <td>
                                        @if ($siblingOrders->isNotEmpty())
                                            <form action="{{ route('purchases.relink-order', $po->id) }}" method="POST" style="display:flex;gap:6px;align-items:center;">
                                                @csrf
                                                <select class="select" name="new_order_id" style="min-width:120px;font-size:12px;">
                                                    @foreach ($siblingOrders as $o)
                                                        <option value="{{ $o->id }}">{{ $o->code }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-secondary btn-sm">付け替え</button>
                                            </form>
                                        @else
                                            <span class="t-muted" style="font-size:12px;">他受注なし</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('purchases.show', $po->id) }}" class="btn btn-secondary btn-sm">詳細</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- フリー発注（同品番・未紐づけ） --}}
            @if ($freePurchaseOrders->isNotEmpty())
                <div style="padding-top:16px;border-top:1px solid var(--border);">
                    <h3 class="card__subtitle" style="font-size:13px;font-weight:600;margin:0 0 6px;">同品番のフリー発注（流用できる可能性あり）</h3>
                    <p class="field-hint" style="margin:0 0 12px;">
                        同品番の発注が {{ $freePurchaseOrders->count() }} 件あります。「使用」を押すと受注に紐づきます（在庫がある場合は引当も追加されます）。
                    </p>
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>発注番号</th>
                                    <th>仕入先</th>
                                    <th class="num">数量</th>
                                    <th>進捗段階</th>
                                    <th>入荷予定</th>
                                    <th style="width:72px;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($freePurchaseOrders as $po)
                                    <tr>
                                        <td class="code-cell">{{ $po->code }}</td>
                                        <td>{{ $po->supplier }}</td>
                                        <td class="num mono">@include('partials.qty', ['qty' => $po->qty, 'productId' => $po->product_id])</td>
                                        <td><span class="badge badge-indigo badge--plain" style="font-size:10.5px;">{{ $po->stage }}</span></td>
                                        <td class="mono">{{ $po->eta }}</td>
                                        <td>
                                            <form action="{{ route('orders.link-purchase', [$order->id, $po->id]) }}" method="POST" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm">使用</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         受注情報（参考）
    ════════════════════════════════════════════════════════ --}}
    <div class="card" style="margin-bottom:16px;">
        <div class="card__head"><h2 class="card__title">受注情報</h2></div>
        <div class="card__body">
            <div class="stat-row">
                <div class="stat-row__item">
                    <div class="stat-row__label">得意先</div>
                    <div class="stat-row__value" style="font-size:15px;">{{ $order->customer }}</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">品番・カラー</div>
                    <div class="stat-row__value code-cell" style="font-size:15px;">
                        <a href="{{ route('inventory.show', $product->id) }}" class="link-strong">{{ $order->sku }}</a>
                    </div>
                    <div class="t-muted" style="font-size:12px;">{{ $order->color }}</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">ステータス</div>
                    <div class="stat-row__value">@include('partials.status', ['status' => $order->status])</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">受注日</div>
                    <div class="stat-row__value mono" style="font-size:15px;">{{ $order->order_date }}</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">納期</div>
                    <div class="stat-row__value mono" style="font-size:15px;">{{ $order->due_date }}</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">販売単価</div>
                    <div class="stat-row__value mono" style="font-size:15px;">¥{{ number_format($product->price) }} / {{ $product->unit }}</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">受注金額</div>
                    <div class="stat-row__value mono" style="font-size:15px;">¥{{ number_format($orderAmount) }}</div>
                    <div class="t-muted" style="font-size:12px;">{{ number_format($product->price) }} × @include('partials.qty', ['qty' => $order->qty, 'productId' => $order->product_id])</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">現在庫（品番全体）</div>
                    <div class="stat-row__value mono">@include('partials.qty', ['qty' => $product->stock, 'productId' => $product->id])</div>
                    <div class="t-muted" style="font-size:12px;">安全在庫 @include('partials.qty', ['qty' => $product->stock_min, 'productId' => $product->id])</div>
                </div>
            </div>
            @if ($order->planned_ship_date ?? false)
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                    <div class="stat-row__label">出荷予定日</div>
                    <div class="mono t-strong" style="font-size:13px;margin-top:4px;">{{ $order->planned_ship_date }}</div>
                </div>
            @endif
            @if ($order->ship_memo)
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                    <div class="stat-row__label">出荷予定日メモ</div>
                    <div class="t-muted" style="font-size:13px;margin-top:4px;">{{ $order->ship_memo }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         出荷予定（出荷確定）
    ════════════════════════════════════════════════════════ --}}
    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <h2 class="card__title">出荷予定（出荷確定）</h2>
            <div style="display:flex;gap:8px;align-items:center;">
                @if ($order->remaining > 0)
                    <a href="{{ route('shipment-plans.create', $order->id) }}" class="btn btn-primary btn-sm">出荷予定を登録</a>
                @endif
            </div>
        </div>
        <div class="card__body{{ $shipmentPlans->isNotEmpty() ? ' card__body--flush' : '' }}">
            <p class="field-hint" style="margin:0 0 12px;">月末在庫予想の減算対象となる出荷コミットです。在庫引当とは別に管理します。</p>
            @if ($shipmentPlans->isEmpty())
                <p class="t-muted" style="margin:0;">出荷予定はまだ登録されていません。</p>
            @else
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>予定番号</th>
                                <th>出荷予定日</th>
                                <th class="num">確定数量</th>
                                <th class="num">出荷済</th>
                                <th class="num">未出荷</th>
                                <th>状態</th>
                                <th>備考</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shipmentPlans as $plan)
                                <tr>
                                    <td class="code-cell t-strong">{{ $plan->code }}</td>
                                    <td class="mono">{{ $plan->planned_ship_date }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $plan->confirmed_qty_m, 'productId' => $order->product_id])</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $plan->shipped_qty_m, 'productId' => $order->product_id])</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $plan->unshipped_qty_m, 'productId' => $order->product_id])</td>
                                    <td><span class="badge badge-indigo badge--plain">{{ $plan->status_label }}</span></td>
                                    <td class="t-muted" style="font-size:12px;">{{ $plan->note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         出荷履歴
    ════════════════════════════════════════════════════════ --}}
    <div class="card">
        <div class="card__head">
            <h2 class="card__title">出荷履歴</h2>
            <span class="t-muted" style="font-size:13px;">{{ $shipments->count() }} 件</span>
        </div>
        <div class="card__body{{ $shipments->isNotEmpty() ? ' card__body--flush' : '' }}">
            @if ($shipments->isEmpty())
                <p class="t-muted" style="margin:0;">出荷記録がありません。</p>
            @else
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>出荷番号</th>
                                <th>出荷日</th>
                                <th class="num">数量</th>
                                <th>出荷先</th>
                                <th>備考</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shipments as $s)
                                <tr>
                                    <td class="code-cell">
                                        <a href="{{ route('shipments.index') }}" class="link-strong">{{ $s->code }}</a>
                                    </td>
                                    <td class="mono">{{ $s->date }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $s->qty, 'productId' => $s->product_id])</td>
                                    <td>{{ $s->ship_to }}</td>
                                    <td class="t-muted" style="font-size:12px;">{{ $s->note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @include('partials.allocation-scripts')

@endsection
