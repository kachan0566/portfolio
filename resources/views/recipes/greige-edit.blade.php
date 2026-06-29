@extends('layouts.app')

@section('title', '生機レシピ編集')
@section('breadcrumb', 'マスタ管理 / 商品レシピ / 生機レシピ編集')

@section('content')
    <div class="page-header">
        <div>
            <h1>生機レシピ編集</h1>
            <p class="lead">「{{ $greige->sku }}」（{{ $greige->name }}）の糸使用量・ロス率・織賃を編集します。</p>
        </div>
        <a href="{{ route('recipes.index', ['tab' => 'greige']) }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card" style="max-width:none;">
        <div class="card__body">
            <form action="{{ route('recipes.greige.update', $greige->sku) }}" method="POST">
                @csrf
                @method('PUT')

                @include('partials.greige-recipe-form', [
                    'materials' => $materials,
                    'lines' => $lines,
                    'lossRate' => $lossRate,
                    'weavingCost' => $weavingCost,
                ])

                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 更新する</button>
                    <a href="{{ route('recipes.index', ['tab' => 'greige']) }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
