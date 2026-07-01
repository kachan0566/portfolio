@extends('layouts.app')

@section('title', '得意先登録')
@section('breadcrumb', 'マスタ管理 / 得意先 / 登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>得意先登録</h1>
            <p class="lead">新しい得意先を登録します。</p>
        </div>
        <a href="{{ route('customers.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card">
        <div class="card__body">
            <form action="{{ route('customers.store') }}" method="POST">
                @csrf
                <div class="field">
                    <label class="label" for="name">得意先名<span class="req">*</span></label>
                    <input class="input" type="text" id="name" name="name" placeholder="例：東レ商事">
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="contact">担当者</label>
                        <input class="input" type="text" id="contact" name="contact" placeholder="例：田中 一郎">
                    </div>
                    <div class="field">
                        <label class="label" for="tel">電話番号</label>
                        <input class="input" type="text" id="tel" name="tel" placeholder="例：03-1111-2222">
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 登録する</button>
                    <a href="{{ route('customers.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
