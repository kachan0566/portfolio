@extends('layouts.app')

@section('title', '糸価格登録')
@section('breadcrumb', 'マスタ管理 / 月別糸価格 / 登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>糸価格登録</h1>
            <p class="lead">糸の年月別単価（円/kg）を登録します。</p>
        </div>
        <a href="{{ route('prices.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card" style="max-width:none;">
        <div class="card__body">
            <form action="{{ route('prices.store') }}" method="POST">
                @csrf
                <div class="field">
                    <label class="label" for="material_id">糸<span class="req">*</span></label>
                    <select class="select" id="material_id" name="material_id" style="max-width:400px;">
                        @foreach ($materials as $m)
                            <option value="{{ $m->id }}" @selected((string) $m->id === (string) old('material_id'))>
                                {{ $m->sku }}（{{ $m->name }}）
                            </option>
                        @endforeach
                    </select>
                    @error('material_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="ym">年月<span class="req">*</span></label>
                        <input class="input" type="month" id="ym" name="ym" value="{{ old('ym') }}" style="max-width:200px;">
                        @error('ym')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label class="label" for="price">単価（円/kg）<span class="req">*</span></label>
                        <input class="input" type="number" id="price" name="price" min="1" step="1" value="{{ old('price') }}" style="max-width:200px;">
                        @error('price')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 登録する</button>
                    <a href="{{ route('prices.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
