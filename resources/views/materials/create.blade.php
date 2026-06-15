@extends('layouts.app')

@section('title', '原材料登録')

@section('content')
    <div class="page-header">
        <h1>原材料登録</h1>
        <a href="{{ route('materials.index') }}" class="btn btn-secondary">一覧に戻る</a>
    </div>

    <form action="{{ route('materials.store') }}" method="POST" class="form-card">
        @csrf

        <div class="field">
            <label for="name" class="label">原材料名</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" class="input">
            @error('name')
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
            <a href="{{ route('materials.index') }}" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
@endsection
