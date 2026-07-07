@extends('layouts.app')

@section('title', '出荷登録')
@section('breadcrumb', '取引 / 出荷処理 / 登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>出荷登録</h1>
            <p class="lead">現在庫引当済みの数量のみ出荷確定できます。発注引当のみの数量は入荷後に出荷可能になります。</p>
        </div>
        <a href="{{ route('shipments.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom:16px;">@include('partials.icon', ['name' => 'alert']) {{ session('error') }}</div>
    @endif

    <div class="grid grid-2">
        <div class="card">
            <div class="card__head">
                <h2 class="card__title">出荷準備中の受注</h2>
            </div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>受注番号</th><th>受注元</th><th>品番</th>
                                <th class="num">出荷可能</th><th class="num">発注引当</th><th>引当状況</th><th>納期</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $o)
                                <tr>
                                    <td class="code-cell"><a href="{{ route('orders.show', $o->id) }}" class="link-strong">{{ $o->code }}</a></td>
                                    <td>{{ $o->customer }}</td>
                                    <td class="code-cell t-strong">{{ $o->sku }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $o->shippable_qty, 'productId' => $o->product_id])</td>
                                    <td class="num mono t-muted">@include('partials.qty', ['qty' => $o->po_allocated, 'productId' => $o->product_id])</td>
                                    <td>
                                        <span class="badge badge-indigo badge--plain" style="font-size:11px;">{{ $o->allocation_status }}</span>
                                        @if ($o->shippable_status)
                                            <span class="badge badge-amber badge--plain" style="font-size:11px;">{{ $o->shippable_status }}</span>
                                        @endif
                                    </td>
                                    <td class="mono">{{ $o->due_date }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card form-card" style="max-width:none;">
            <div class="card__head"><h2 class="card__title">出荷内容</h2></div>
            <div class="card__body">
                @php
                    $selectedOrder = $pending->firstWhere('id', $selectedOrderId) ?? $pending->first();
                    $selectedMetersPerTan = \App\Support\DemoData::findProduct($selectedOrder?->product_id)?->meters_per_tan ?? 50;
                    $selectedShippable = $selectedOrder?->shippable_qty ?? 0;
                @endphp
                <form action="{{ route('shipments.store') }}" method="POST" id="shipment-form">
                    @csrf
                    <div class="field">
                        <label class="label" for="order">受注元（対象受注）<span class="req">*</span></label>
                        <select class="select" id="order" name="order_id">
                            @foreach ($pending as $o)
                                @php $perTan = \App\Support\DemoData::findProduct($o->product_id)?->meters_per_tan ?? 50; @endphp
                                <option value="{{ $o->id }}"
                                        data-shippable="{{ $o->shippable_qty }}"
                                        data-meters-per-tan="{{ $perTan }}"
                                        data-order-mode="{{ $o->order_qty_mode ?? 'tan' }}"
                                        data-fifo-preview="{{ collect($o->fifo_preview ?? [])->map(fn ($r) => ($r->code ?? '').' '.number_format((float)$r->actual_qty_m,1).'m')->implode(' / ') }}"
                                        @selected($selectedOrderId === $o->id)>
                                    {{ $o->code }} ／ {{ $o->customer }}（出荷可 {{ \App\Support\QtyHelper::format($o->shippable_qty, $o->product_id) }}）
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label class="label" for="qty-display">出荷数量<span class="req">*</span></label>
                            @include('partials.qty-input', [
                                'tanName' => 'qty_tan',
                                'metersName' => 'qty_meters',
                                'id' => 'qty-display',
                                'valueTan' => 0,
                                'productId' => null,
                                'metersPerTan' => $selectedMetersPerTan,
                                'tanStep' => \App\Support\QtyHelper::ORDER_PO_TAN_STEP,
                                'maxTan' => $selectedShippable > 0 ? \App\Support\QtyHelper::tanCount($selectedShippable, $selectedOrder?->product_id) : null,
                                'pageKey' => 'shipments-form',
                                'showMeterSwitch' => false,
                            ])
                            <p class="field-hint" id="shippable-hint"></p>
                            <div id="fifo-preview" class="field-hint" style="margin-top:8px;"></div>
                        </div>
                        <div class="field">
                            <label class="label" for="date">出荷日<span class="req">*</span></label>
                            <input class="input" type="date" id="date" name="date" value="2026-06-15">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="ship_to">出荷先<span class="req">*</span></label>
                        <input class="input" type="text" id="ship_to" name="ship_to" placeholder="例：○○倉庫">
                    </div>
                    <div class="field">
                        <label class="label" for="note">備考</label>
                        <textarea class="textarea" id="note" name="note" rows="2" placeholder="時間指定・分納など"></textarea>
                    </div>
                    <p class="field-hint">登録すると対象品番の在庫が出荷数量だけ減少します（現在庫引当の範囲内のみ）。</p>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 出荷を登録</button>
                        <a href="{{ route('shipments.index') }}" class="btn btn-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.qty-unit-loader')
    <script>
    (function () {
        const orderSelect = document.getElementById('order');
        const qtyField = document.querySelector('[data-qty-unit-field][data-page-key="shipments-form"]');
        const hint = document.getElementById('shippable-hint');
        const api = QtyUnit.initPage('shipments-form');

        const fifoPreview = document.getElementById('fifo-preview');

        function updateHint() {
            const opt = orderSelect?.selectedOptions[0];
            const max = parseInt(opt?.dataset.shippable || '0', 10);
            const perTan = parseInt(opt?.dataset.metersPerTan || '50', 10);
            const isMeters = (opt?.dataset.orderMode || 'tan') === 'meters';
            if (qtyField) {
                qtyField.dataset.metersPerTan = String(perTan);
                qtyField.hidden = isMeters;
                if (max > 0 && !isMeters) {
                    qtyField.dataset.maxMeters = String(max);
                } else {
                    qtyField.removeAttribute('data-max-meters');
                }
                if (!isMeters) {
                    api.setMetersPerTan(perTan);
                }
            }
            if (hint) {
                hint.textContent = isMeters
                    ? 'm受注です。FIFOで足りる反が自動選択され、実測mで出荷されます。'
                    : (max > 0
                        ? 'この受注は現在庫引当の範囲で最大 ' + QtyUnit.formatQty(max, perTan) + ' まで出荷できます（整数反・FIFO自動）。'
                        : '現在庫引当がないため出荷できません。');
            }
            if (fifoPreview) {
                const preview = opt?.dataset.fifoPreview || '';
                fifoPreview.textContent = preview ? 'FIFO候補: ' + preview : '';
            }
        }

        orderSelect?.addEventListener('change', updateHint);
        updateHint();
    })();
    </script>
@endpush
