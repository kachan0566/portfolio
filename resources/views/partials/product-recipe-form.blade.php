@php
    $processingCostValue = old('processing_cost', $processingCost ?? 0);
@endphp

@if (!empty($greigeSku))
    <div class="field">
        <label class="label">生機品番</label>
        <p id="greige-sku-label" class="t-strong code-cell" style="margin:0;font-size:15px;">
            {{ $greigeSku }}
            @if (!empty($greigeName))
                <span class="t-muted">（{{ $greigeName }}）</span>
            @endif
        </p>
        <p class="field-hint">
            糸の使用量・織賃は
            <a href="{{ route('recipes.index', ['tab' => 'greige']) }}">生機レシピタブ</a>
            で管理します。生機単価はそちらから自動参照されます。
        </p>
    </div>
@endif

<div class="field">
    <label class="label" for="processing_cost">染色加工料（円/m）<span class="req">*</span></label>
    <input
        class="input"
        type="number"
        id="processing_cost"
        name="processing_cost"
        min="0"
        step="1"
        value="{{ $processingCostValue }}"
        style="max-width:200px;"
    >
    @error('processing_cost')<p class="field-error">{{ $message }}</p>@enderror
    <p class="field-hint">染色・仕上げなどの加工費用を1mあたりの金額で入力します。</p>
</div>

@include('partials.recipe-profit-panel', [
    'ym' => $ym ?? null,
    'breakdown' => $breakdown ?? null,
    'profit' => $profit ?? null,
    'price' => $price ?? null,
    'costWarnings' => $costWarnings ?? [],
])
