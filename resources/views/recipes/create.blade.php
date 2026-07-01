@extends('layouts.app')

@section('title', 'レシピ登録')
@section('breadcrumb', 'マスタ管理 / 商品レシピ / 登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>レシピ登録</h1>
            <p class="lead">商品に使用する原材料と使用量を登録します。</p>
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
                            <option value="{{ $p->id }}" @selected((string) $p->id === (string) old('product_id'))>
                                {{ $p->sku }}（{{ $p->color }}）
                            </option>
                        @endforeach
                    </select>
                </div>

                @include('partials.recipe-lines', ['materials' => $materials])

                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 登録する</button>
                    <a href="{{ route('recipes.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
