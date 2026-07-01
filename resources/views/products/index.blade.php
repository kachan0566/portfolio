@extends('layouts.app')

@section('title', '商品管理')
@section('breadcrumb', 'マスタ管理 / 商品管理')

@section('content')
    <div class="page-header">
        <div>
            <h1>商品管理</h1>
            <p class="lead">生機品番（親）と製品品番（子）を管理します。販売価格は製品品番ごとに設定します。</p>
        </div>
        <a href="{{ route('products.create') }}" class="btn btn-primary">
            @include('partials.icon', ['name' => 'plus']) 製品品番を登録
        </a>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <h2 class="card__title">検索</h2>
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'sku' => ['label' => '品番', 'placeholder' => '製品品番・生機品番'],
            ],
        ])
    </div>

    @php
        $greigeMap = $greiges->keyBy('sku');
        $groups = $products->groupBy('greige_sku');
    @endphp
    @forelse ($groups as $greigeSku => $items)
        @php $greige = $greigeMap->get($greigeSku); @endphp
        <div class="card" style="margin-bottom:16px;">
            <div class="card__head">
                <h2 class="card__title">
                    <span class="code-cell" style="font-size:15px;">{{ $greigeSku }}</span>
                    <span class="t-muted" style="font-weight:500;">（生機：{{ $items->first()->greige_name }}）</span>
                </h2>
                <div style="display:flex;gap:8px;align-items:center;">
                    <span class="badge badge-indigo badge--plain">{{ $greige?->meters_per_tan ?? \App\Support\DemoData::METERS_PER_TAN_GREIGE }}m/反（生機）</span>
                    <span class="badge badge-indigo badge--plain">製品 {{ $items->count() }} 品番</span>
                </div>
            </div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>製品品番</th>
                                <th>カラー</th>
                                <th class="num">1反あたり</th>
                                <th class="num">販売価格</th>
                                <th>単位</th>
                                <th class="num">現在庫</th>
                                <th style="width:120px;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $product)
                                <tr>
                                    <td class="code-cell t-strong">{{ $product->sku }}</td>
                                    <td>{{ $product->color }}</td>
                                    <td class="num mono">{{ $product->meters_per_tan }}m/反</td>
                                    <td class="num mono">{{ number_format($product->price) }} 円</td>
                                    <td class="t-muted">{{ $product->unit }}</td>
                                    <td class="num mono">
                                        @include('partials.qty', ['qty' => $product->stock, 'productId' => $product->id])
                                        @if ($product->stock < $product->stock_min)
                                            <span class="badge badge-rose" style="margin-left:6px;">不足</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="{{ route('products.edit', $product->id) }}" class="btn btn-secondary btn-sm">編集</a>
                                            <form action="{{ route('products.destroy', $product->id) }}" method="POST" class="inline-form" onsubmit="return confirm('「{{ $product->sku }}」を削除しますか？')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">削除</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card__body">
                <p class="t-muted" style="text-align:center;margin:0;">条件に一致する商品はありません。</p>
            </div>
        </div>
    @endforelse
@endsection
