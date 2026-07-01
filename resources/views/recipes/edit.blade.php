@extends('layouts.app')

@section('title', 'レシピ編集')
@section('breadcrumb', 'マスタ管理 / 商品レシピ / 編集')

@section('content')
    <div class="page-header">
        <div>
            <h1>レシピ編集</h1>
            <p class="lead">「{{ $product->sku }}」（{{ $product->color }}）の原材料と使用量を編集します。</p>
        </div>
        <a href="{{ route('recipes.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card" style="max-width:none;">
        <div class="card__body">
            <form action="{{ route('recipes.update', $product->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="field">
                    <label class="label">品番</label>
                    <p class="t-strong code-cell" style="margin:0;font-size:15px;">{{ $product->sku }}（{{ $product->color }}）</p>
                </div>

                @include('partials.recipe-lines', ['materials' => $materials, 'lines' => $items])

                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 更新する</button>
                    <a href="{{ route('recipes.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
