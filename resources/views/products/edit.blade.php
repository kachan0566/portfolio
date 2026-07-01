@extends('layouts.app')

@section('title', '商品編集')
@section('breadcrumb', 'マスタ管理 / 商品管理 / 編集')

@section('content')
    <div class="page-header">
        <div>
            <h1>製品品番編集</h1>
            <p class="lead">「{{ $product->sku }}」の情報を編集します。</p>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card">
        <div class="card__body">
            <form action="{{ route('products.update', $product->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="field">
                    <label class="label" for="greige">生機品番（親）<span class="req">*</span></label>
                    <select class="select" id="greige" name="greige_sku">
                        @foreach ($greiges as $g)
                            <option value="{{ $g->sku }}" @selected($g->sku === $product->greige_sku)>{{ $g->sku }}（{{ $g->name }}）</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="sku">製品品番<span class="req">*</span></label>
                        <input class="input" type="text" id="sku" name="sku" value="{{ old('sku', $product->sku) }}">
                    </div>
                    <div class="field">
                        <label class="label" for="color">カラー<span class="req">*</span></label>
                        <input class="input" type="text" id="color" name="color" value="{{ old('color', $product->color) }}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="category">カテゴリ<span class="req">*</span></label>
                        <select class="select" id="category" name="category">
                            @foreach ($categories as $c)
                                <option value="{{ $c->name }}" @selected($c->name === $product->category)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="unit">単位<span class="req">*</span></label>
                        <input class="input" type="text" id="unit" name="unit" value="{{ old('unit', $product->unit) }}">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="price">販売価格<span class="req">*</span></label>
                    <div class="input-group" style="max-width:280px;">
                        <input class="input" type="number" id="price" name="price" min="0" value="{{ old('price', $product->price) }}">
                        <span class="input-group__suffix">円</span>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 更新する</button>
                    <a href="{{ route('products.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
