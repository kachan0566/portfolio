@extends('layouts.app')

@section('title', '売上・粗利')
@section('breadcrumb', '集計 / 売上・粗利')

@section('content')
    @php
        $tabQuery = array_filter([
            'ym' => $ym,
            'sku' => $search['sku'] !== '' ? $search['sku'] : null,
            'product_id' => $selectedProductId,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <div class="page-header">
        <div>
            <h1>売上・粗利</h1>
            <p class="lead">
                @if ($tab === 'forecast')
                    今月の売上・出荷見通しを品番別に確認・入力します（対象月: {{ $ym }}）。
                @else
                    出荷実績をもとに、売上・製造コスト・粗利を品番別に集計します（対象月: {{ $ym }}）。
                @endif
                @if ($selectedProduct)
                    <span class="t-muted">選択中: {{ $selectedProduct->sku }}</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <form method="GET" action="{{ route('sales.index') }}" class="sales-month-form">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @foreach ($search as $key => $value)
                    @if ($value !== '')
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                @if ($selectedProductId)
                    <input type="hidden" name="product_id" value="{{ $selectedProductId }}">
                @endif
                <label class="sales-month-form__label" for="sales-ym">対象月</label>
                <select class="select" id="sales-ym" name="ym" onchange="this.form.submit()">
                    @foreach ($monthOptions as $optionYm)
                        <option value="{{ $optionYm }}" @selected($optionYm === $ym)>{{ $optionYm }}</option>
                    @endforeach
                </select>
            </form>
            <button class="btn btn-secondary" disabled>@include('partials.icon', ['name' => 'download']) 売上一覧CSV</button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">
            @include('partials.icon', ['name' => 'check'])
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @include('sales.partials.progress-summary', ['forecast' => $forecast, 'ym' => $ym, 'selectedProduct' => $selectedProduct ?? null])

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head" style="padding-bottom:0;border-bottom:none;">
            <div style="display:flex;gap:4px;border-bottom:1px solid var(--border);width:100%;padding-bottom:0;flex-wrap:wrap;">
                <a href="{{ route('sales.index', array_merge($tabQuery, ['tab' => 'actual'])) }}"
                   class="btn btn-ghost btn-sm"
                   style="border-radius:6px 6px 0 0;margin-bottom:-1px;{{ $tab === 'actual' ? 'border:1px solid var(--border);border-bottom-color:var(--surface);background:var(--surface);font-weight:600;' : 'border:1px solid transparent;' }}">
                    実績
                </a>
                <a href="{{ route('sales.index', array_merge($tabQuery, ['tab' => 'forecast'])) }}"
                   class="btn btn-ghost btn-sm"
                   style="border-radius:6px 6px 0 0;margin-bottom:-1px;{{ $tab === 'forecast' ? 'border:1px solid var(--border);border-bottom-color:var(--surface);background:var(--surface);font-weight:600;' : 'border:1px solid transparent;' }}">
                    見通し
                </a>
            </div>
        </div>
        <div class="card__body">
            @if ($tab === 'forecast')
                @include('sales.partials.forecast-tab')
            @else
                @include('sales.partials.actual-tab')
            @endif
        </div>
    </div>
@endsection
