@php
    $lossRatePercent = old('loss_rate_percent', isset($lossRate) ? round((float) $lossRate * 100, 2) : 3);
    $weavingCostValue = old('weaving_cost', $weavingCost ?? '');
@endphp

<div class="field">
    <label class="label" for="loss_rate_percent">ロス率（%）<span class="req">*</span></label>
    <input
        class="input"
        type="number"
        id="loss_rate_percent"
        name="loss_rate_percent"
        min="0"
        max="99"
        step="0.01"
        value="{{ $lossRatePercent }}"
        style="max-width:160px;"
    >
    @error('loss_rate')<p class="field-error">{{ $message }}</p>@enderror
    <p class="field-hint">織りロスなどをマスタ固定で設定します。必要糸量 = 理論量 × (1 + ロス率)。</p>
</div>

<div class="field">
    <label class="label" for="weaving_cost">織賃（円/m）<span class="req">*</span></label>
    <input
        class="input"
        type="number"
        id="weaving_cost"
        name="weaving_cost"
        min="0"
        step="1"
        value="{{ $weavingCostValue }}"
        style="max-width:160px;"
    >
    @error('weaving_cost')<p class="field-error">{{ $message }}</p>@enderror
    <p class="field-hint">1mあたりの織賃。糸原価と合算して生機単価（円/m）になります。</p>
</div>

@include('partials.recipe-lines', [
    'materials' => $materials,
    'lines' => $lines ?? [],
    'showProcessingCost' => false,
])
