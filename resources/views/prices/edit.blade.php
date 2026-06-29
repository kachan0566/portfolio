@extends('layouts.app')

@section('title', '糸価格編集')
@section('breadcrumb', 'マスタ管理 / 月別糸価格 / 編集')

@section('content')
    <div class="page-header">
        <div>
            <h1>糸価格編集</h1>
            <p class="lead">{{ $price->material }}（{{ $price->material_sku }}）の {{ $price->ym }} 単価を編集します。</p>
        </div>
        <a href="{{ route('prices.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card" style="max-width:none;">
        <div class="card__body">
            <form action="{{ route('prices.update', $price->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-row">
                    <div class="field">
                        <label class="label">糸</label>
                        <p class="t-strong" style="margin:0;">{{ $price->material_sku }}（{{ $price->material }}）</p>
                    </div>
                    <div class="field">
                        <label class="label">年月</label>
                        <p class="mono t-strong" style="margin:0;">{{ $price->ym }}</p>
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="price">単価（円/kg）<span class="req">*</span></label>
                    <input class="input" type="number" id="price" name="price" min="1" step="1" value="{{ old('price', $price->price) }}" style="max-width:200px;">
                    @error('price')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 更新する</button>
                    <a href="{{ route('prices.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
