@extends('layouts.app')

@section('title', '発注登録')
@section('breadcrumb', '取引 / 発注管理 / 登録')

@section('content')
    @php
        $typeLabel = \App\Support\PurchaseOrderType::label($type);
    @endphp
    <div class="page-header">
        <div>
            <h1>{{ $typeLabel }}の登録</h1>
            <p class="lead">依頼先・出荷先・納期と数量を入力します。</p>
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

                @if ($type === \App\Support\PurchaseOrderType::YARN)
                    <div class="form-row">
                        <div class="field">
                            <label class="label" for="material_id">糸品番<span class="req">*</span></label>
                            <select class="select" id="material_id" name="material_id" required>
                                @foreach ($yarnMaterials as $m)
                                    <option value="{{ $m->id }}" @selected((string) old('material_id') === (string) $m->id)>
                                        {{ $m->sku }}（{{ $m->name }}）
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="qty_kg">発注数量（kg）<span class="req">*</span></label>
                            <input class="input mono" type="number" id="qty_kg" name="qty_kg" step="0.01" min="0.01"
                                   value="{{ old('qty_kg') }}" required>
                        </div>
                    </div>
                @elseif ($type === \App\Support\PurchaseOrderType::GREIGE)
                    <div class="form-row">
                        <div class="field">
                            <label class="label" for="greige_sku">生機品番<span class="req">*</span></label>
                            <select class="select" id="greige_sku" name="greige_sku" required data-greige-select>
                                @foreach ($greiges as $g)
                                    <option value="{{ $g->sku }}" @selected(old('greige_sku') === $g->sku)>
                                        {{ $g->sku }}（{{ $g->name }}）
                                    </option>
                                @endforeach
                            </select>
                            <p class="field-hint">レシピ登録済みの生機品番のみ選択できます。</p>
                        </div>
                        <div class="field">
                            <label class="label">標準反長（m/反）</label>
                            <input class="input mono" type="text" id="greige_meters_per_tan" readonly value="—">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label class="label" for="qty_tan">発注反数<span class="req">*</span></label>
                            <input class="input mono" type="number" id="qty_tan" name="qty_tan" step="1" min="1"
                                   value="{{ old('qty_tan') }}" required data-greige-tan>
                        </div>
                        <div class="field">
                            <label class="label">総m数（自動）</label>
                            <input class="input mono" type="text" id="greige_total_m" readonly value="—">
                        </div>
                    </div>
                    <div class="card" style="margin:0 0 16px;background:var(--bg-subtle, #f8fafc);">
                        <div class="card__body">
                            <h3 class="card__title" style="font-size:14px;margin:0 0 8px;">必要糸量（プレビュー）</h3>
                            <div id="yarn-requirements-preview" class="t-muted" style="font-size:13px;">生機品番と反数を入力すると表示されます。</div>
                        </div>
                    </div>
                @else
                    <div class="form-row">
                        <div class="field">
                            <label class="label" for="product_id">製品品番<span class="req">*</span></label>
                            <select class="select" id="product_id" name="product_id" required data-product-select>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}"
                                            @selected($sourceOrder && $p->id === $sourceOrder->product_id || (string) old('product_id') === (string) $p->id)>
                                        {{ $p->sku }}（{{ $p->color }}）
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="label">標準反長（m/反）</label>
                            <input class="input mono" type="text" id="product_meters_per_tan" readonly value="—">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label class="label" for="product_qty_tan">発注反数<span class="req">*</span></label>
                            <input class="input mono" type="number" id="product_qty_tan" name="product_qty_tan" step="1" min="1"
                                   value="{{ old('product_qty_tan') }}" data-product-tan>
                        </div>
                        <div class="field">
                            <label class="label">総m数（自動）</label>
                            <input type="hidden" name="qty_meters" id="qty_meters" value="{{ old('qty_meters', $suggestedMeters ?? '') }}">
                            <input class="input mono" type="text" id="product_total_m" readonly value="—">
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
@endsection

@push('scripts')
<script>
window.PURCHASE_GREIGE_META = {!! $greigeMetaJson !!};
window.PURCHASE_PRODUCT_META = {!! $productMetaJson !!};
</script>
<script src="{{ asset('js/purchase-form.js') }}"></script>
@endpush
