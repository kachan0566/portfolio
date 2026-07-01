@extends('layouts.app')

@section('title', '商品登録')
@section('breadcrumb', 'マスタ管理 / 商品管理 / 登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>製品品番登録</h1>
            <p class="lead">生機品番（親）に紐づく製品品番（子）を登録します。</p>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card">
        <div class="card__body">
            <form action="{{ route('products.store') }}" method="POST">
                @csrf
                <div class="field">
                    <label class="label" for="greige">生機品番（親）<span class="req">*</span></label>
                    <select class="select" id="greige" name="greige_sku">
                        @foreach ($greiges as $g)
                            <option value="{{ $g->sku }}">{{ $g->sku }}（{{ $g->name }}）</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="sku">製品品番<span class="req">*</span></label>
                        <input class="input" type="text" id="sku" name="sku" placeholder="例：FAB-A-BK" value="{{ old('sku') }}">
                    </div>
                    <div class="field">
                        <label class="label" for="color">カラー<span class="req">*</span></label>
                        <input class="input" type="text" id="color" name="color" placeholder="例：ブラック" value="{{ old('color') }}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="category">カテゴリ<span class="req">*</span></label>
                        <select class="select" id="category" name="category">
                            @foreach ($categories as $c)
                                <option value="{{ $c->name }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="unit">単位<span class="req">*</span></label>
                        <input class="input" type="text" id="unit" name="unit" placeholder="例：反" value="{{ old('unit', '反') }}">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="price">販売価格<span class="req">*</span></label>
                    <div class="input-group" style="max-width:280px;">
                        <input class="input" type="number" id="price" name="price" min="0" placeholder="1200" value="{{ old('price') }}">
                        <span class="input-group__suffix">円</span>
                    </div>
                    <p class="field-hint">製品品番ごとの販売単価を入力します。売上計算に使用されます。</p>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 登録する</button>
                    <a href="{{ route('products.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
