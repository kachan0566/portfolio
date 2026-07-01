@extends('layouts.app')

@section('title', '商品レシピ')
@section('breadcrumb', 'マスタ管理 / 商品レシピ')

@section('content')
    <div class="page-header">
        <div>
            <h1>商品レシピ</h1>
            <p class="lead">品番ごとに使用する原材料と使用量を登録します。染料・仕上げ剤は加工料としてまとめて表示します。</p>
        </div>
        <a href="{{ route('recipes.create') }}" class="btn btn-primary">
            @include('partials.icon', ['name' => 'plus']) レシピを登録
        </a>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head"><h2 class="card__title">検索</h2></div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'sku' => ['label' => '品番'],
            ],
        ])
    </div>

    <div class="grid grid-equal-2">
        @foreach ($recipes as $sku => $recipe)
            <div class="card">
                <div class="card__head">
                    <h2 class="card__title code-cell" style="font-size:15px;">{{ $sku }}</h2>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span class="badge badge-green badge--plain">単位製造コスト {{ number_format($unitCosts[$sku] ?? 0) }} 円</span>
                        <a href="{{ route('recipes.edit', $recipe->product_id) }}" class="btn btn-secondary btn-sm">編集</a>
                    </div>
                </div>
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr><th>品番</th><th class="num">使用量</th><th class="num">{{ $ym }}単価</th><th class="num">金額</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($recipe->rows as $row)
                                    <tr>
                                        <td>
                                            <span class="code-cell t-strong">{{ $row->label }}</span>
                                            @if ($row->sub)
                                                <div class="t-muted" style="font-size:11.5px;">{{ $row->sub }}</div>
                                            @endif
                                        </td>
                                        <td class="num mono">
                                            @if ($row->qty !== null)
                                                {{ rtrim(rtrim(number_format($row->qty, 2), '0'), '.') }} {{ $row->unit }}
                                            @else
                                                <span class="t-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="num mono t-muted">
                                            @if ($row->price !== null)
                                                {{ number_format($row->price) }} 円
                                            @else
                                                <span class="t-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="num mono">{{ number_format($row->amount) }} 円</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="data-foot">
                                <tr>
                                    <td colspan="3">1単位あたりの製造コスト</td>
                                    <td class="num mono">{{ number_format($unitCosts[$sku] ?? 0) }} 円</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
