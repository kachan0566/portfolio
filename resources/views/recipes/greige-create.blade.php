@extends('layouts.app')

@section('title', '生機レシピ登録')
@section('breadcrumb', 'マスタ管理 / 商品レシピ / 生機レシピ登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>生機レシピ登録</h1>
            <p class="lead">生機品番ごとに織りに必要な糸の使用量（kg/m）、ロス率、織賃（円/m）を登録します。</p>
        </div>
        <a href="{{ route('recipes.index', ['tab' => 'greige']) }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card" style="max-width:none;">
        <div class="card__body">
            <form action="{{ route('recipes.greige.store') }}" method="POST">
                @csrf
                <div class="field">
                    <label class="label" for="greige_sku">生機品番<span class="req">*</span></label>
                    <select class="select" id="greige_sku" name="greige_sku" style="max-width:400px;">
                        @foreach ($greiges as $g)
                            <option value="{{ $g->sku }}" @selected((string) $g->sku === (string) old('greige_sku'))>
                                {{ $g->sku }}（{{ $g->name }}）
                            </option>
                        @endforeach
                    </select>
                    @error('greige_sku')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                @include('partials.greige-recipe-form', ['materials' => $materials])

                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 登録する</button>
                    <a href="{{ route('recipes.index', ['tab' => 'greige']) }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
