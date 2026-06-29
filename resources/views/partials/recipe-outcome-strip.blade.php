@php
    $priceAmount = $price ?? $profit?->price ?? 0;
    $profitAmount = $profit?->profit;
    $marginPercent = $profit?->margin_percent;
    $profitTone = $profitAmount === null
        ? ''
        : ($profitAmount < 0 ? 'is-negative' : 'is-positive');
@endphp

<div class="recipe-outcome-strip">
    <div class="recipe-outcome-strip__item recipe-outcome-strip__item--price">
        <div class="recipe-outcome-strip__label">販売価格</div>
        <div class="recipe-outcome-strip__value mono" @if (!empty($dynamic)) data-profit-price @endif>
            {{ number_format($priceAmount) }} 円/m
        </div>
    </div>
    <div
        class="recipe-outcome-strip__item recipe-outcome-strip__item--profit {{ $profitTone }}"
        @if (!empty($dynamic)) data-profit-row="profit" @endif
    >
        <div class="recipe-outcome-strip__label">粗利</div>
        <div class="recipe-outcome-strip__value mono" @if (!empty($dynamic)) data-profit-amount @endif>
            @if ($profitAmount !== null)
                {{ number_format($profitAmount) }} 円/m
            @else
                <span class="t-muted">算出不可</span>
            @endif
        </div>
        <div class="recipe-outcome-strip__sub mono" @if (!empty($dynamic)) data-profit-margin @endif>
            @if ($marginPercent !== null)
                粗利率 {{ $marginPercent }} %
            @else
                <span class="t-muted">粗利率 —</span>
            @endif
        </div>
    </div>
</div>
