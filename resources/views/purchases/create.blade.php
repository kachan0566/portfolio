@extends('layouts.app')

@section('title', '発注登録')
@section('breadcrumb', '取引 / 発注管理 / 登録')

@section('content')
    @php
        $typeLabel = \App\Support\PurchaseOrderType::label($type);
        $oldLines = old('lines', [[]]);
        if ($oldLines === [] || $oldLines === [[]]) {
            $oldLines = [[]];
        }
    @endphp
    <div class="page-header">
        <div>
            <h1>{{ $typeLabel }}の登録</h1>
            <p class="lead">依頼先・出荷先・納期と数量を入力します。複数品目は明細行を追加してください。</p>
        </div>
        <a href="{{ route('purchases.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="tabs" style="margin-bottom:16px;display:flex;gap:8px;">
        @foreach (\App\Support\PurchaseOrderType::all() as $t)
            <a href="{{ route('purchases.create', array_filter(['type' => $t, 'order_id' => $t === 'product' ? request('order_id') : null])) }}"
               class="btn {{ $type === $t ? 'btn-primary' : 'btn-secondary' }} btn-sm">
                {{ \App\Support\PurchaseOrderType::label($t) }}
            </a>
        @endforeach
    </div>

    @if ($sourceOrder)
        <div class="alert alert-info" style="margin-bottom:16px;">
            受注 <strong class="code-cell">{{ $sourceOrder->code }}</strong> から製品発注を作成します。
            <a href="{{ route('orders.show', $sourceOrder->id) }}" class="link-strong" style="margin-left:8px;">受注詳細</a>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px;">
            <ul style="margin:0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card form-card" style="max-width:none;">
        <div class="card__body">
            <form action="{{ route('purchases.store') }}" method="POST" id="purchase-form">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                @if ($sourceOrder)
                    <input type="hidden" name="order_id" value="{{ $sourceOrder->id }}">
                @endif

                <div class="form-row">
                    <div class="field">
                        <label class="label" for="supplier_id">依頼先<span class="req">*</span></label>
                        <select class="select" id="supplier_id" name="supplier_id" required>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" @selected((string) old('supplier_id') === (string) $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="ship_to_id">出荷先<span class="req">*</span></label>
                        <select class="select" id="ship_to_id" name="ship_to_id" required>
                            @foreach ($shipTos as $st)
                                <option value="{{ $st->id }}" @selected((string) old('ship_to_id') === (string) $st->id)>{{ $st->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="card" style="margin:0 0 16px;background:var(--bg-subtle, #f8fafc);">
                    <div class="card__head" style="display:flex;justify-content:space-between;align-items:center;">
                        <h2 class="card__title" style="font-size:14px;margin:0;">発注明細行</h2>
                        <button type="button" class="btn btn-secondary btn-sm" id="add-po-line" data-po-type="{{ $type }}">
                            + 行を追加
                        </button>
                    </div>
                    <div class="card__body" id="po-lines-container">
                        @foreach ($oldLines as $index => $line)
                            @include('purchases.partials.line-row', [
                                'type' => $type,
                                'index' => $index,
                                'line' => is_array($line) ? $line : [],
                                'yarnMaterials' => $yarnMaterials,
                                'greiges' => $greiges,
                                'products' => $products,
                                'sourceOrder' => $sourceOrder,
                                'suggestedMeters' => $suggestedMeters,
                                'showRemove' => $index > 0,
                            ])
                        @endforeach
                    </div>
                </div>

                @if ($type === \App\Support\PurchaseOrderType::GREIGE)
                    <div class="card" style="margin:0 0 16px;background:var(--bg-subtle, #f8fafc);">
                        <div class="card__body">
                            <h3 class="card__title" style="font-size:14px;margin:0 0 8px;">必要糸量（プレビュー・合計）</h3>
                            <div id="yarn-requirements-preview" class="t-muted" style="font-size:13px;">明細行を入力すると表示されます。</div>
                        </div>
                    </div>
                @endif

                <div class="form-row">
                    <div class="field">
                        <label class="label" for="order_date">発注日<span class="req">*</span></label>
                        <input class="input" type="date" id="order_date" name="order_date"
                               value="{{ old('order_date', $defaultDate) }}" required>
                    </div>
                    <div class="field">
                        <label class="label" for="due_date">納期<span class="req">*</span></label>
                        <input class="input" type="date" id="due_date" name="due_date"
                               value="{{ old('due_date', $sourceOrder?->due_date) }}" required>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" name="save_action" value="draft" class="btn btn-secondary">
                        下書き保存
                    </button>
                    <button type="submit" name="save_action" value="ordered" class="btn btn-primary">
                        @include('partials.icon', ['name' => 'check']) 発注確定
                    </button>
                    <a href="{{ route('purchases.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>

    <template id="po-line-template">
        @include('purchases.partials.line-row', [
            'type' => $type,
            'index' => '__INDEX__',
            'line' => [],
            'yarnMaterials' => $yarnMaterials,
            'greiges' => $greiges,
            'products' => $products,
            'sourceOrder' => $sourceOrder,
            'suggestedMeters' => null,
            'showRemove' => true,
        ])
    </template>
@endsection

@push('scripts')
<script>
window.PURCHASE_GREIGE_META = {!! $greigeMetaJson !!};
window.PURCHASE_PRODUCT_META = {!! $productMetaJson !!};
window.PURCHASE_TYPE = @json($type);
</script>
<script src="{{ asset('js/purchase-form.js') }}"></script>
<script src="{{ asset('js/purchase-lines.js') }}"></script>
@endpush
