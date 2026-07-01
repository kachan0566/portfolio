<form method="POST" action="{{ route('inventory.forecast.adjustments') }}" class="form-row" style="align-items:flex-end;gap:12px;flex-wrap:wrap;" id="forecast-adj-form-{{ $formId ?? 'list' }}">
    @csrf
    <input type="hidden" name="target_ym" value="{{ $targetYm }}">
    @if (! empty($redirect))
        <input type="hidden" name="redirect" value="{{ $redirect }}">
    @endif
    @if ($productId ?? null)
        <input type="hidden" name="product_id" value="{{ $productId }}">
        @php
            $adjProductId = $productId;
            $adjMetersPerTan = \App\Support\DemoData::findProduct($productId)?->meters_per_tan ?? 50;
        @endphp
    @else
        <div class="field" style="min-width:140px;">
            <label class="label" for="adj-product-{{ $formId ?? 'list' }}">品番</label>
            <select class="select" id="adj-product-{{ $formId ?? 'list' }}" name="product_id" required data-adj-product-select>
                @foreach ($productOptions as $line)
                    <option value="{{ $line->product_id }}" data-meters-per-tan="{{ \App\Support\DemoData::findProduct($line->product_id)?->meters_per_tan ?? 50 }}">{{ $line->sku }}</option>
                @endforeach
            </select>
        </div>
        @php
            $firstLine = $productOptions->first();
            $adjProductId = $firstLine?->product_id;
            $adjMetersPerTan = \App\Support\DemoData::findProduct($adjProductId)?->meters_per_tan ?? 50;
        @endphp
    @endif
    <div class="field" style="min-width:100px;">
        <label class="label" for="adj-direction-{{ $formId ?? 'list' }}">増減</label>
        <select class="select" id="adj-direction-{{ $formId ?? 'list' }}" name="direction" required>
            <option value="increase">増加</option>
            <option value="decrease">減少</option>
        </select>
    </div>
    <div class="field" style="min-width:160px;">
        <label class="label" for="adj-qty-{{ $formId ?? 'list' }}">数量（反）</label>
        @include('partials.qty-input', [
            'name' => 'qty',
            'id' => 'adj-qty-'.($formId ?? 'list'),
            'valueMeters' => 0,
            'metersPerTan' => $adjMetersPerTan,
            'pageKey' => 'forecast-adj-'.($formId ?? 'list'),
            'compact' => true,
        ])
    </div>
    <div class="field" style="flex:1;min-width:200px;">
        <label class="label" for="adj-reason-{{ $formId ?? 'list' }}">調整理由</label>
        <input class="input" type="text" id="adj-reason-{{ $formId ?? 'list' }}" name="reason" required placeholder="例）染工場からの連絡により入荷が2日遅れ">
    </div>
    <button type="submit" class="btn btn-secondary">登録</button>
</form>
@if (! ($productId ?? null))
    @push('scripts')
        @include('partials.qty-unit-loader')
        <script>
        (function () {
            const formId = @json($formId ?? 'list');
            const pageKey = 'forecast-adj-' + formId;
            const select = document.getElementById('adj-product-' + formId);
            const api = QtyUnit.initPage(pageKey);
            const field = document.querySelector('[data-qty-unit-field][data-page-key="' + pageKey + '"]');
            select?.addEventListener('change', function () {
                const perTan = parseInt(select.selectedOptions[0]?.dataset.metersPerTan || '50', 10);
                if (field) field.dataset.metersPerTan = String(perTan);
                api.setMetersPerTan(perTan);
            });
        })();
        </script>
    @endpush
@else
    @push('scripts')
        @include('partials.qty-unit-loader')
        <script>QtyUnit.initPage(@json('forecast-adj-'.($formId ?? 'list')));</script>
    @endpush
@endif
