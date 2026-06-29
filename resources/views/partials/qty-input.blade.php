@php
    $name = $name ?? 'qty';
    $valueMeters = (int) ($valueMeters ?? 0);
    $metersPerTan = (int) ($metersPerTan ?? 50);
    $pageKey = $pageKey ?? 'default';
    $id = $id ?? $name;
    $maxMeters = isset($maxMeters) ? (int) $maxMeters : null;
    $placeholder = $placeholder ?? '0';
@endphp
<div class="qty-unit-field"
     data-qty-unit-field
     data-page-key="{{ $pageKey }}"
     data-meters-per-tan="{{ $metersPerTan }}"
     @if ($maxMeters !== null) data-max-meters="{{ $maxMeters }}" @endif>
    <input type="hidden" name="{{ $name }}" value="{{ $valueMeters }}" data-qty-meters-hidden>
    <div class="input-group">
        <input class="input mono"
               type="number"
               id="{{ $id }}"
               data-qty-display
               min="0"
               step="0.01"
               placeholder="{{ $placeholder }}"
               autocomplete="off">
        <span class="input-group__suffix" data-qty-suffix>反</span>
    </div>
    <p class="field-hint qty-unit-field__hint" data-qty-hint></p>
</div>
