<form method="POST" action="{{ route('inventory.greige-forecast.adjustments') }}" class="form-row" style="align-items:flex-end;gap:12px;flex-wrap:wrap;" id="greige-forecast-adj-form-{{ $formId ?? 'list' }}">
    @csrf
    <input type="hidden" name="target_ym" value="{{ $targetYm }}">
    @if (! empty($redirect))
        <input type="hidden" name="redirect" value="{{ $redirect }}">
    @endif
    @if ($greigeSku ?? null)
        <input type="hidden" name="greige_sku" value="{{ $greigeSku }}">
        @php
            $adjGreigeSku = $greigeSku;
            $adjMetersPerTan = \App\Support\DemoData::findGreige($greigeSku)?->meters_per_tan ?? \App\Support\DemoData::METERS_PER_TAN_GREIGE;
        @endphp
    @else
        <div class="field" style="min-width:140px;">
            <label class="label" for="adj-greige-{{ $formId ?? 'list' }}">生機品番</label>
            <select class="select" id="adj-greige-{{ $formId ?? 'list' }}" name="greige_sku" required data-adj-greige-select>
                @foreach ($greigeOptions as $line)
                    @php $sku = (string) ($line->greige_sku ?? $line->sku); @endphp
                    <option value="{{ $sku }}" data-meters-per-tan="{{ \App\Support\DemoData::findGreige($sku)?->meters_per_tan ?? \App\Support\DemoData::METERS_PER_TAN_GREIGE }}">{{ $sku }}</option>
                @endforeach
            </select>
        </div>
        @php
            $firstLine = $greigeOptions->first();
            $adjGreigeSku = (string) ($firstLine->greige_sku ?? $firstLine->sku ?? '');
            $adjMetersPerTan = \App\Support\DemoData::findGreige($adjGreigeSku)?->meters_per_tan ?? \App\Support\DemoData::METERS_PER_TAN_GREIGE;
        @endphp
    @endif
    <div class="field" style="min-width:100px;">
        <label class="label" for="adj-greige-direction-{{ $formId ?? 'list' }}">増減</label>
        <select class="select" id="adj-greige-direction-{{ $formId ?? 'list' }}" name="direction" required>
            <option value="increase">増加</option>
            <option value="decrease">減少</option>
        </select>
    </div>
    <div class="field" style="min-width:160px;">
        <label class="label" for="adj-greige-qty-{{ $formId ?? 'list' }}">数量（反）</label>
        @include('partials.qty-input', [
            'name' => 'qty',
            'id' => 'adj-greige-qty-'.($formId ?? 'list'),
            'valueMeters' => 0,
            'metersPerTan' => $adjMetersPerTan,
            'pageKey' => 'greige-forecast-adj-'.($formId ?? 'list'),
            'compact' => true,
            'isGreige' => true,
            'greigeSku' => $adjGreigeSku,
        ])
    </div>
    <div class="field" style="flex:1;min-width:200px;">
        <label class="label" for="adj-greige-reason-{{ $formId ?? 'list' }}">調整理由</label>
        <input class="input" type="text" id="adj-greige-reason-{{ $formId ?? 'list' }}" name="reason" required placeholder="例）織工場からの連絡で入荷が遅れる見込み">
    </div>
    <button type="submit" class="btn btn-secondary">登録</button>
</form>
@if (! ($greigeSku ?? null))
    @push('scripts')
        @include('partials.qty-unit-loader')
        <script>
        (function () {
            const formId = @json($formId ?? 'list');
            const pageKey = 'greige-forecast-adj-' + formId;
            const select = document.getElementById('adj-greige-' + formId);
            const api = QtyUnit.initPage(pageKey);
            const field = document.querySelector('[data-qty-unit-field][data-page-key="' + pageKey + '"]');
            select?.addEventListener('change', function () {
                const perTan = parseInt(select.selectedOptions[0]?.dataset.metersPerTan || '{{ \App\Support\DemoData::METERS_PER_TAN_GREIGE }}', 10);
                if (field) field.dataset.metersPerTan = String(perTan);
                api.setMetersPerTan(perTan);
            });
        })();
        </script>
    @endpush
@else
    @push('scripts')
        @include('partials.qty-unit-loader')
        <script>QtyUnit.initPage(@json('greige-forecast-adj-'.($formId ?? 'list')));</script>
    @endpush
@endif
