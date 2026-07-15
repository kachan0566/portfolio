@extends('layouts.app')

@section('title', '入荷登録')
@section('breadcrumb', '取引 / 入荷処理 / 登録')

@section('content')
    @php
        use App\Support\PurchaseOrderType;
        use App\Support\DemoData;
        $typeLabel = PurchaseOrderType::label($type);
        $qtyUnit = $type === PurchaseOrderType::YARN ? 'kg' : 'm';
        $useMultiLine = DemoData::usesPurchaseOrderDatabase() && $poLinesJson !== '{}';
    @endphp
    <div class="page-header">
        <div>
            <h1>入荷登録</h1>
            <p class="lead">{{ $typeLabel }}の入荷を登録します。1回の入荷で複数の発注明細行を選択できます。</p>
        </div>
        <a href="{{ route('receivings.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="tabs" style="margin-bottom:16px;display:flex;gap:8px;">
        @foreach (PurchaseOrderType::all() as $t)
            <a href="{{ route('receivings.create', ['type' => $t]) }}"
               class="btn {{ $type === $t ? 'btn-primary' : 'btn-secondary' }} btn-sm">{{ PurchaseOrderType::label($t) }}</a>
        @endforeach
    </div>

    @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <div class="grid grid-2">
        <div class="card">
            <div class="card__head">
                <h2 class="card__title">入荷待ちの{{ $typeLabel }}</h2>
            </div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>発注番号</th><th>仕入先</th><th>品番</th><th class="num">残数</th><th>状態</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($pending as $po)
                                @php $rem = \App\Support\DemoState::poRemaining($po->id); @endphp
                                <tr>
                                    <td class="code-cell">{{ $po->code }}</td>
                                    <td>{{ $po->supplier }}</td>
                                    <td class="code-cell t-strong">{{ $po->sku }}</td>
                                    <td class="num mono">
                                        @if ($type === PurchaseOrderType::YARN)
                                            {{ number_format($rem, 2) }} kg
                                        @else
                                            @include('partials.purchase-qty', ['purchase' => (object) ['type' => $type, 'qty_meters' => $rem, 'greige_sku' => $po->greige_sku ?? null, 'product_id' => $po->product_id ?? null]])
                                        @endif
                                    </td>
                                    <td><span class="badge badge-indigo badge--plain">{{ $po->status_label ?? $po->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty">入荷待ちの発注はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card form-card" style="max-width:none;">
            <div class="card__head"><h2 class="card__title">入荷内容</h2></div>
            <div class="card__body">
                @if ($pending->isEmpty())
                    <p class="t-muted" style="margin:0;">入荷対象の発注がありません。</p>
                @else
                    <form action="{{ route('receivings.store') }}" method="POST" id="receiving-form">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">
                        <div class="field">
                            <label class="label" for="po">対象発注<span class="req">*</span></label>
                            <select class="select" id="po" name="po_id">
                                @foreach ($pending as $po)
                                    @php $rem = \App\Support\DemoState::poRemaining($po->id); @endphp
                                    <option value="{{ $po->id }}">
                                        {{ $po->code }} ／ {{ $po->sku }}
                                        （残 {{ $type === PurchaseOrderType::YARN ? number_format($rem, 2).' kg' : number_format($rem).' m' }}）
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @if ($useMultiLine)
                            <div id="receiving-lines-panel" class="card" style="margin:0 0 16px;background:var(--bg-subtle,#f8fafc);">
                                <div class="card__head">
                                    <h3 class="card__title" style="font-size:14px;">入荷する明細行</h3>
                                </div>
                                <div class="card__body card__body--flush" id="receiving-lines-body">
                                    <p class="t-muted" style="padding:12px;margin:0;">発注を選択すると明細行が表示されます。</p>
                                </div>
                            </div>
                        @else
                            <div class="form-row">
                                @if ($type === PurchaseOrderType::YARN)
                                    <div class="field">
                                        <label class="label" for="qty">入荷数量（kg）<span class="req">*</span></label>
                                        <input class="input" type="number" id="qty" name="qty" min="0.01" step="0.01" placeholder="100.5">
                                    </div>
                                @else
                                    <div class="field">
                                        <label class="label" for="receiving-qty">入荷数量<span class="req">*</span></label>
                                        @php
                                            $firstPo = $pending->first();
                                            $metersPerTan = $type === PurchaseOrderType::GREIGE
                                                ? ($firstPo->meters_per_tan ?? 100)
                                                : (\App\Support\DemoData::findProduct((int) ($firstPo->product_id ?? 0))?->meters_per_tan ?? 50);
                                        @endphp
                                        @include('partials.qty-input', [
                                            'tanName' => 'qty_tan',
                                            'metersName' => 'qty_meters',
                                            'id' => 'receiving-qty',
                                            'valueTan' => 0,
                                            'metersPerTan' => $metersPerTan,
                                            'tanStep' => \App\Support\QtyHelper::RECEIVING_TAN_STEP,
                                            'showMeterSwitch' => false,
                                            'submitMeters' => false,
                                            'pageKey' => 'receiving-form',
                                            'placeholder' => '2',
                                        ])
                                        <p class="field-hint">反数は 0.25反刻み。下の表で反ごとに実測mを入力してください。</p>
                                    </div>
                                @endif
                            </div>
                            @if ($type !== PurchaseOrderType::YARN)
                                <div class="card" style="margin:0 0 16px;background:var(--bg-subtle, #f8fafc);">
                                    <div class="card__head"><h3 class="card__title" style="font-size:14px;">反明細（実測m）</h3></div>
                                    <div class="card__body card__body--flush">
                                        <div class="table-wrap">
                                            <table class="data" id="receiving-rolls-table">
                                                <thead>
                                                    <tr><th>#</th><th>反数</th><th class="num">実測m</th></tr>
                                                </thead>
                                                <tbody id="receiving-rolls-body"></tbody>
                                            </table>
                                        </div>
                                        <p class="field-hint" style="padding:8px 12px;margin:0;">
                                            合計: <span id="receiving-tan-sum" class="mono">0.00</span>反 /
                                            実測 <span id="receiving-m-sum" class="mono">—</span>m
                                        </p>
                                    </div>
                                </div>
                            @endif
                        @endif

                        <div class="field">
                            <label class="label" for="date">入荷日<span class="req">*</span></label>
                            <input class="input" type="date" id="date" name="date" value="2026-06-15">
                        </div>

                        <p class="field-hint">
                            @if ($type === PurchaseOrderType::YARN)
                                登録すると糸在庫（kg）が増加します。
                            @elseif ($type === PurchaseOrderType::GREIGE)
                                登録すると染工場の生機在庫が増加し、反明細に織り上がり実測 m が記録されます。
                            @else
                                登録すると製品在庫が増加し、反明細に染め上がり実測 m が記録されます。発注引当がある場合は自動で現在庫引当に変換されます。
                            @endif
                        </p>

                        @if ($useMultiLine)
                            @include('partials.qty-unit-loader')
                            <script src="{{ asset('js/receiving-rolls.js') }}"></script>
                            <script src="{{ asset('js/receiving-multi-lines.js') }}"></script>
                            <script>
                                ReceivingMultiLines.init({
                                    poSelect: document.getElementById('po'),
                                    linesBody: document.getElementById('receiving-lines-body'),
                                    poLines: {!! $poLinesJson !!},
                                    type: @json($type),
                                    tanStep: {{ \App\Support\QtyHelper::RECEIVING_TAN_STEP }},
                                });
                            </script>
                        @elseif ($type !== PurchaseOrderType::YARN)
                            @include('partials.qty-unit-loader')
                            <script src="{{ asset('js/receiving-rolls.js') }}"></script>
                            <script>
                                QtyUnit.initPage('receiving-form');
                                ReceivingRolls.init({
                                    qtyField: document.querySelector('[data-qty-unit-field][data-page-key="receiving-form"]'),
                                    tbody: document.getElementById('receiving-rolls-body'),
                                    tanSumEl: document.getElementById('receiving-tan-sum'),
                                    mSumEl: document.getElementById('receiving-m-sum'),
                                    metersPerTan: {{ $metersPerTan ?? 50 }},
                                });
                            </script>
                        @endif

                        <div class="actions">
                            <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 入荷を登録</button>
                            <a href="{{ route('receivings.index') }}" class="btn btn-secondary">キャンセル</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
