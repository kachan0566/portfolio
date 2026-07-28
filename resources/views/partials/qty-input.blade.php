@php
    $tanName = $tanName ?? ($name ?? 'qty_tan');
    $metersName = $metersName ?? 'qty_meters';
    $productId = $productId ?? null;
    $isGreige = $isGreige ?? false;
    $greigeSku = $greigeSku ?? null;
    $metersPerTan = isset($metersPerTan)
        ? (int) $metersPerTan
        : \App\Support\QtyHelper::metersPerTan($productId, $isGreige, $greigeSku);
    $valueMeters = isset($valueMeters) ? (int) $valueMeters : null;
    $valueTan = isset($valueTan)
        ? (float) $valueTan
        : ($valueMeters !== null && $valueMeters > 0
            ? \App\Support\QtyHelper::roundTan($valueMeters / max(1, $metersPerTan))
            : 0.0);
    if ($valueMeters === null && $valueTan > 0) {
        $valueMeters = (int) round(\App\Support\QtyHelper::roundTan($valueTan) * $metersPerTan);
    }
    $valueMeters = (int) ($valueMeters ?? 0);
    $pageKey = $pageKey ?? 'default';
    $id = $id ?? null;
    $maxTan = isset($maxTan)
        ? (float) $maxTan
        : (isset($maxMeters) ? \App\Support\QtyHelper::roundTan((int) $maxMeters / max(1, $metersPerTan)) : null);
    $placeholder = $placeholder ?? '0';
    $compact = $compact ?? false;
    $fieldClass = $fieldClass ?? '';
    $showMeterSwitch = $showMeterSwitch ?? true;
    $submitMeters = $submitMeters ?? true;
    $tanStep = $tanStep ?? \App\Support\QtyHelper::TAN_STEP;
    $tanDecimals = $tanStep >= 1 ? 0 : 2;
@endphp
<div class="qty-unit-field{{ $compact ? ' qty-unit-field--compact' : '' }} {{ $fieldClass }}"
     data-qty-unit-field
     data-page-key="{{ $pageKey }}"
     data-qty-mode="tan"
     data-meters-per-tan="{{ $metersPerTan }}"
     data-tan-step="{{ $tanStep }}"
     @if ($maxTan !== null) data-max-tan="{{ \App\Support\QtyHelper::formatTanCount($maxTan) }}" @endif>
    <input type="hidden" name="{{ $tanName }}" value="{{ \App\Support\QtyHelper::formatTanCount($valueTan) }}" data-qty-tan-hidden>
    @if ($submitMeters)
        <input type="hidden" name="{{ $metersName }}" value="{{ $valueMeters > 0 ? $valueMeters : '' }}" data-qty-meters-hidden>
    @else
        <input type="hidden" value="{{ $valueMeters > 0 ? $valueMeters : '' }}" data-qty-meters-hidden>
    @endif
    <div data-qty-tan-row>
        <div class="input-group{{ $compact ? ' po-line__input-group' : '' }}">
            <input class="input mono"
                   type="number"
                   @if ($id) id="{{ $id }}" @endif
                   data-qty-tan-display
                   min="0"
                   step="{{ $tanStep }}"
                   placeholder="{{ $placeholder }}"
                   autocomplete="off">
            <span class="input-group__suffix">反</span>
        </div>
    </div>
    <div data-qty-meter-row hidden>
        <div class="input-group{{ $compact ? ' po-line__input-group' : '' }}">
            <input class="input mono"
                   type="number"
                   data-qty-meter-display
                   min="0"
                   step="1"
                   placeholder="0"
                   autocomplete="off">
            <span class="input-group__suffix">m</span>
        </div>
    </div>
    <p class="field-hint qty-unit-field__hint" data-qty-hint></p>
    @if ($showMeterSwitch)
        <button type="button" class="qty-unit-field__switch" data-qty-mode-switch>mで直接指定</button>
    @endif
</div>
