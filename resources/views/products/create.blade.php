@extends('layouts.app')

@section('title', '商品登録')

@section('content')
    <div class="page-header">
        <h1>商品登録</h1>
        <a href="{{ route('products.index') }}" class="btn btn-secondary">一覧に戻る</a>
    </div>

    <form action="{{ route('products.store') }}" method="POST" class="form-card">
        @csrf

        <div class="field">
            <label for="name" class="label">商品名</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" class="input">
            @error('name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="sku" class="label">品番</label>
            <input type="text" name="sku" id="sku" value="{{ old('sku') }}" class="input">
            @error('sku')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="price" class="label">販売価格</label>
            <input type="number" name="price" id="price" value="{{ old('price') }}" min="0" class="input">
            @error('price')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="category" class="label">カテゴリ</label>
            <input type="text" name="category" id="category" value="{{ old('category') }}" class="input">
            @error('category')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="unit" class="label">単位</label>
            <input type="text" name="unit" id="unit" value="{{ old('unit') }}" class="input">
            @error('unit')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">登録する</button>
            <a href="{{ route('products.index') }}" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
@endsection
