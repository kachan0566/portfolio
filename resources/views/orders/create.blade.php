@extends('layouts.app')

@section('title', '受注登録')
@section('breadcrumb', '取引 / 受注管理 / 登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>受注登録</h1>
            <p class="lead">得意先からの新しい受注を登録します。</p>
        </div>
        <a href="{{ route('orders.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card">
        <div class="card__body">
            <form action="{{ route('orders.store') }}" method="POST">
                @csrf
                <div class="field">
                    <label class="label" for="customer">得意先<span class="req">*</span></label>
                    <select class="select" id="customer" name="customer_id">
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="product">品番<span class="req">*</span></label>
                        <select class="select" id="product" name="product_id">
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->sku }}（{{ $p->color }}）</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="qty">数量<span class="req">*</span></label>
                        <input class="input" type="number" id="qty" name="qty" min="1" placeholder="100">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="order_date">受注日<span class="req">*</span></label>
                        <input class="input" type="date" id="order_date" name="order_date" value="2026-06-15">
                    </div>
                    <div class="field">
                        <label class="label" for="due_date">納期<span class="req">*</span></label>
                        <input class="input" type="date" id="due_date" name="due_date">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="ship_memo">出荷予定日メモ</label>
                    <textarea class="textarea" id="ship_memo" name="ship_memo" rows="3" placeholder="例）6/18 午前に分納予定 / 入荷待ち など"></textarea>
                    <p class="field-hint">出荷予定日に関する補足を自由に記入できます。</p>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 登録する</button>
                    <a href="{{ route('orders.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
