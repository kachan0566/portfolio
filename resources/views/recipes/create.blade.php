@extends('layouts.app')

@section('title', 'レシピ登録')
@section('breadcrumb', 'マスタ管理 / 商品レシピ / 登録')

@section('content')
  @php
      use App\Support\DemoData;

      $selected = $products->firstWhere('id', (int) old('product_id')) ?? $products->first();
      $selectedBreakdown = $selected
          ? DemoData::unitCostBreakdown($selected->id, $ym)
          : null;
      $selectedProfit = $selected
          ? DemoData::unitProfitSummary($selected->id, $ym, null, (int) old('processing_cost', 0))
          : null;
      $selectedWarnings = $selected
          ? DemoData::costWarningMessages($selected->id, $ym)
          : [];
  @endphp

    <div class="page-header">
        <div>
            <h1>製品レシピ登録</h1>
            <p class="lead">製品品番ごとに染色加工料（円/m）と販売価格を登録します。糸・織賃は紐づく生機品番の生機レシピから参照されます。</p>
        </div>
        <a href="{{ route('recipes.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card" style="max-width:none;">
        <div class="card__body">
            <form action="{{ route('recipes.store') }}" method="POST">
                @csrf
                <div class="field">
                    <label class="label" for="product">品番<span class="req">*</span></label>
                    <select class="select" id="product" name="product_id" style="max-width:400px;">
                        @foreach ($products as $p)
                            <option
                                value="{{ $p->id }}"
                                data-greige-sku="{{ $p->greige_sku }}"
                                data-greige-name="{{ $p->greige_name }}"
                                data-price="{{ $p->price }}"
                                data-greige-cost="{{ $p->cost_calculable && $p->greige_cost !== null ? $p->greige_cost : '' }}"
                                data-calculable="{{ $p->cost_calculable ? '1' : '0' }}"
                                @selected((string) $p->id === (string) old('product_id'))
                            >
                                {{ $p->sku }}（{{ $p->color }}）
                            </option>
                        @endforeach
                    </select>
                    @error('product_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div id="greige-info">
                    @include('partials.product-recipe-form', [
                        'processingCost' => old('processing_cost', 0),
                        'price' => old('price', $selected->price ?? 0),
                        'greigeSku' => $selected->greige_sku ?? null,
                        'greigeName' => $selected->greige_name ?? null,
                        'breakdown' => $selectedBreakdown,
                        'profit' => $selectedProfit,
                        'ym' => $ym,
                        'costWarnings' => $selectedWarnings,
                    ])
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 登録する</button>
                    <a href="{{ route('recipes.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/recipe-profit.js') }}"></script>
<script>
(function () {
    const select = document.getElementById('product');
    const label = document.getElementById('greige-sku-label');
    if (!select) return;

    select.addEventListener('change', function () {
        const opt = select.selectedOptions[0];
        if (!opt) return;

        if (label) {
            const sku = opt.dataset.greigeSku || '';
            const name = opt.dataset.greigeName || '';
            label.innerHTML = sku + (name ? ' <span class="t-muted">（' + name + '）</span>' : '');
        }

        if (typeof window.recipeProfitSyncProduct === 'function') {
            window.recipeProfitSyncProduct(opt);
        }
    });
})();
</script>
@endpush
