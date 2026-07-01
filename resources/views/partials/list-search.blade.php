@php
    $fields = $fields ?? [];
    $params = $params ?? [];
    $hasActive = \App\Support\ListSearch::isActive($params);
@endphp
<form class="filter-bar" method="GET" action="{{ $action ?? request()->url() }}">
    <div class="filter-bar__grid">
        @if (! empty($fields['code']))
            <div class="filter-bar__field">
                <label class="filter-bar__label" for="search-code">{{ $fields['code']['label'] ?? '番号' }}</label>
                <input class="input" type="text" id="search-code" name="code"
                       value="{{ $params['code'] ?? '' }}"
                       placeholder="{{ $fields['code']['placeholder'] ?? '' }}">
            </div>
        @endif

        @if (! empty($fields['customer']))
            <div class="filter-bar__field">
                <label class="filter-bar__label" for="search-customer">{{ $fields['customer']['label'] ?? '得意先' }}</label>
                <input class="input" type="text" id="search-customer" name="customer"
                       value="{{ $params['customer'] ?? '' }}"
                       placeholder="{{ $fields['customer']['placeholder'] ?? '得意先名' }}">
            </div>
        @endif

        @if (! empty($fields['supplier']))
            <div class="filter-bar__field">
                <label class="filter-bar__label" for="search-supplier">{{ $fields['supplier']['label'] ?? '仕入先' }}</label>
                <input class="input" type="text" id="search-supplier" name="supplier"
                       value="{{ $params['supplier'] ?? '' }}"
                       placeholder="{{ $fields['supplier']['placeholder'] ?? '仕入先名' }}">
            </div>
        @endif

        @if (! empty($fields['sku']))
            <div class="filter-bar__field">
                <label class="filter-bar__label" for="search-sku">{{ $fields['sku']['label'] ?? '品番' }}</label>
                <input class="input" type="text" id="search-sku" name="sku"
                       value="{{ $params['sku'] ?? '' }}"
                       placeholder="{{ $fields['sku']['placeholder'] ?? '品番' }}">
            </div>
        @endif

        @if (! empty($fields['due']))
            <div class="filter-bar__field filter-bar__field--range">
                <label class="filter-bar__label">{{ $fields['due']['label'] ?? '納期' }}</label>
                <div class="filter-bar__range">
                    <input class="input" type="date" name="due_from" value="{{ $params['due_from'] ?? '' }}" aria-label="納期（開始）">
                    <span class="filter-bar__sep">〜</span>
                    <input class="input" type="date" name="due_to" value="{{ $params['due_to'] ?? '' }}" aria-label="納期（終了）">
                </div>
            </div>
        @endif

        @if (! empty($fields['status']))
            <div class="filter-bar__field">
                <label class="filter-bar__label" for="search-status">{{ $fields['status']['label'] ?? 'ステータス' }}</label>
                <select class="select" id="search-status" name="status">
                    <option value="">すべて</option>
                    @foreach ($fields['status']['options'] as $value => $label)
                        <option value="{{ $value }}" @selected(($params['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <div class="filter-bar__actions">
        <button type="submit" class="btn btn-primary btn-sm">
            @include('partials.icon', ['name' => 'search']) 検索
        </button>
        @if ($hasActive)
            <a href="{{ $action ?? request()->url() }}" class="btn btn-secondary btn-sm">条件をクリア</a>
        @endif
    </div>
</form>
