@extends('layouts.app')

@section('title', '仕入先登録')
@section('breadcrumb', 'マスタ管理 / 仕入先 / 登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>仕入先登録</h1>
            <p class="lead">新しい仕入先を登録します。</p>
        </div>
        <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card">
        <div class="card__body">
            <form action="{{ route('suppliers.store') }}" method="POST">
                @csrf
                <div class="field">
                    <label class="label" for="name">仕入先名<span class="req">*</span></label>
                    <input class="input" type="text" id="name" name="name" placeholder="例：紡績ワークス">
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="contact">担当者</label>
                        <input class="input" type="text" id="contact" name="contact" placeholder="例：伊藤 健">
                    </div>
                    <div class="field">
                        <label class="label" for="tel">電話番号</label>
                        <input class="input" type="text" id="tel" name="tel" placeholder="例：03-9999-0000">
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 登録する</button>
                    <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
