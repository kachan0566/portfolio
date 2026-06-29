@extends('layouts.app')

@section('title', '商品レシピ')
@section('breadcrumb', 'マスタ管理 / 商品レシピ')

@section('content')
    <div class="page-header">
        <div>
            <h1>商品レシピ</h1>
            <p class="lead">製品品番の染色加工料と製造コスト、生機品番の織り用レシピ（糸・織賃・生機単価）を管理します。</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if ($tab === 'greige')
                <a href="{{ route('recipes.greige.create') }}" class="btn btn-primary">
                    @include('partials.icon', ['name' => 'plus']) 生機レシピを登録
                </a>
            @else
                <a href="{{ route('recipes.create') }}" class="btn btn-primary">
                    @include('partials.icon', ['name' => 'plus']) 製品レシピを登録
                </a>
            @endif
        </div>
    </div>

    <div class="tabs" style="margin-bottom:16px;display:flex;gap:8px;">
        <a href="{{ route('recipes.index', array_merge(request()->except('tab'), ['tab' => 'product'])) }}"
           class="btn {{ $tab === 'product' ? 'btn-primary' : 'btn-secondary' }} btn-sm">製品レシピ</a>
        <a href="{{ route('recipes.index', array_merge(request()->except('tab'), ['tab' => 'greige'])) }}"
           class="btn {{ $tab === 'greige' ? 'btn-primary' : 'btn-secondary' }} btn-sm">生機レシピ</a>
    </div>

    @if ($tab === 'product')
        @include('partials.cost-warning', ['warnings' => $costWarnings])
    @else
        @include('partials.cost-warning', [
            'warnings' => $greigeCostWarnings,
            'heading' => '生機単価を算出できない項目があります。',
        ])
    @endif

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head"><h2 class="card__title">検索</h2></div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'sku' => ['label' => '品番', 'placeholder' => $tab === 'greige' ? '生機品番' : '製品品番'],
            ],
            'hidden' => ['tab' => $tab],
        ])
    </div>

    @if ($tab === 'greige')
        <div class="grid grid-equal-2">
            @forelse ($greigeRecipes as $recipe)
                @php($breakdown = $recipe->cost_breakdown)
                <div class="card">
                    <div class="card__head">
                        <h2 class="card__title code-cell" style="font-size:15px;">{{ $recipe->greige_sku }}</h2>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span class="badge badge-indigo badge--plain">ロス率 {{ rtrim(rtrim(number_format($recipe->loss_rate * 100, 2), '0'), '.') }}%</span>
                            @if ($breakdown->calculable)
                                <span class="badge badge-green badge--plain">生機単価 {{ number_format($breakdown->total) }} 円/m</span>
                            @else
                                <span class="badge badge-rose badge--plain">生機単価算出不可</span>
                            @endif
                            <a href="{{ route('recipes.greige.edit', $recipe->greige_sku) }}" class="btn btn-secondary btn-sm">編集</a>
                        </div>
                    </div>
                    <div class="card__body card__body--flush">
                        <p class="t-muted" style="margin:0;padding:12px 16px 0;font-size:13px;">{{ $recipe->greige_name }}</p>
                        <div class="table-wrap">
                            <table class="data">
                                <thead>
                                    <tr><th>糸品番</th><th class="num">使用量</th><th class="num">{{ $ym }}単価</th><th class="num">金額</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($breakdown->yarn_lines as $row)
                                        <tr>
                                            <td>
                                                <span class="code-cell t-strong">{{ $row->label }}</span>
                                                @if ($row->sub)
                                                    <div class="t-muted" style="font-size:11.5px;">{{ $row->sub }}</div>
                                                @endif
                                            </td>
                                            <td class="num mono">
                                                {{ rtrim(rtrim(number_format($row->qty, 2), '0'), '.') }} kg/m
                                                @if ($breakdown->loss_rate > 0)
                                                    <div class="t-muted" style="font-size:11px;">ロス込 {{ rtrim(rtrim(number_format($row->effective_qty, 2), '0'), '.') }} kg/m</div>
                                                @endif
                                            </td>
                                            <td class="num mono t-muted">
                                                @if ($row->price !== null)
                                                    {{ number_format($row->price) }} 円/kg
                                                @else
                                                    <span class="t-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="num mono">
                                                @if ($row->amount !== null)
                                                    {{ number_format($row->amount) }} 円/m
                                                @else
                                                    <span class="t-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="data-foot">
                                    <tr>
                                        <td colspan="3">糸原価小計（ロス率込み）</td>
                                        <td class="num mono">
                                            @if ($breakdown->yarn_cost !== null)
                                                {{ number_format($breakdown->yarn_cost) }} 円/m
                                            @else
                                                <span class="t-muted">算出不可</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3">織賃</td>
                                        <td class="num mono">{{ number_format($breakdown->weaving_cost) }} 円/m</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3">生機単価合計</td>
                                        <td class="num mono">
                                            @if ($breakdown->total !== null)
                                                {{ number_format($breakdown->total) }} 円/m
                                            @else
                                                <span class="t-muted">算出不可</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card" style="grid-column:1/-1;">
                    <div class="card__body"><p class="t-muted" style="margin:0;">生機レシピが登録されていません。</p></div>
                </div>
            @endforelse
        </div>
    @else
        <div class="grid grid-equal-2">
            @foreach ($recipes as $sku => $recipe)
                @php($breakdown = $recipe->breakdown)
                @php($profit = $recipe->profit)
                <div class="card">
                    <div class="card__head">
                        <h2 class="card__title code-cell" style="font-size:15px;">{{ $sku }}</h2>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            @if ($breakdown->calculable)
                                <span class="badge badge-green badge--plain">単位製造コスト {{ number_format($breakdown->total) }} 円/m</span>
                            @else
                                <span class="badge badge-rose badge--plain">製造コスト算出不可</span>
                            @endif
                            <span class="badge badge-indigo badge--plain">販売価格 {{ number_format($profit->price) }} 円/m</span>
                            @if ($profit->profit !== null)
                                <span class="badge {{ $profit->profit < 0 ? 'badge-rose' : 'badge-green' }} badge--plain">
                                    粗利 {{ number_format($profit->profit) }} 円/m · {{ $profit->margin_percent }}%
                                </span>
                            @else
                                <span class="badge badge-rose badge--plain">粗利算出不可</span>
                            @endif
                            <a href="{{ route('recipes.edit', $recipe->product_id) }}" class="btn btn-secondary btn-sm">編集</a>
                        </div>
                    </div>
                    <div class="card__body card__body--flush">
                        <div class="table-wrap">
                            <table class="data">
                                <thead>
                                    <tr><th>項目</th><th class="num">金額</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            生機単価
                                            @if ($breakdown->greige_sku)
                                                <div class="t-muted" style="font-size:11.5px;">
                                                    <span class="code-cell">{{ $breakdown->greige_sku }}</span>
                                                    @if ($breakdown->greige_name)
                                                        （{{ $breakdown->greige_name }}）
                                                    @endif
                                                    ·
                                                    <a href="{{ route('recipes.index', ['tab' => 'greige']) }}">詳細は生機レシピへ</a>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="num mono">
                                            @if ($breakdown->greige_cost !== null)
                                                {{ number_format($breakdown->greige_cost) }} 円/m
                                            @elseif ($breakdown->missing_greige_recipe)
                                                <span class="t-muted">算出不可（生機レシピ未登録）</span>
                                            @else
                                                <span class="t-muted">算出不可</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>染色加工料</td>
                                        <td class="num mono">{{ number_format($breakdown->processing_cost) }} 円/m</td>
                                    </tr>
                                    <tr class="recipe-cost-total">
                                        <td>製造コスト合計</td>
                                        <td class="num mono">
                                            @if ($breakdown->total !== null)
                                                {{ number_format($breakdown->total) }} 円/m
                                            @else
                                                <span class="t-muted">算出不可</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @include('partials.recipe-outcome-strip', ['profit' => $profit])
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
