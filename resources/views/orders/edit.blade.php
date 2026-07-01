@extends('layouts.app')

@section('title', '受注編集')
@section('breadcrumb', '取引 / 受注管理 / 編集')

@section('content')
    <div class="page-header">
        <div>
            <h1>受注編集</h1>
            <p class="lead">{{ $order->code }}（{{ $order->customer }}）を編集します。</p>
        </div>
        <a href="{{ route('orders.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card">
        <div class="card__body">
            <form action="{{ route('orders.update', $order->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="field">
                    <label class="label" for="customer">得意先<span class="req">*</span></label>
                    <select class="select" id="customer" name="customer_id">
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected($c->name === $order->customer)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="product">品番<span class="req">*</span></label>
                        <select class="select" id="product" name="product_id">
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected($p->sku === $order->sku)>{{ $p->sku }}（{{ $p->color }}）</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="qty">数量<span class="req">*</span></label>
                        <input class="input" type="number" id="qty" name="qty" min="1" value="{{ $order->qty }}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="order_date">受注日<span class="req">*</span></label>
                        <input class="input" type="date" id="order_date" name="order_date" value="{{ $order->order_date }}">
                    </div>
                    <div class="field">
                        <label class="label" for="due_date">納期<span class="req">*</span></label>
                        <input class="input" type="date" id="due_date" name="due_date" value="{{ $order->due_date }}">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="ship_memo">出荷予定日メモ</label>
                    <textarea class="textarea" id="ship_memo" name="ship_memo" rows="3" placeholder="例）6/18 午前に分納予定 / 入荷待ち など">{{ $order->ship_memo }}</textarea>
                    <p class="field-hint">出荷予定日に関する補足を自由に記入できます。</p>
                </div>
                <div class="field">
                    <label class="label" for="status">ステータス</label>
                    <select class="select" id="status" name="status" style="max-width:240px;">
                        @foreach (['未出荷', '一部出荷', '出荷済み'] as $st)
                            <option value="{{ $st }}" @selected($st === $order->status)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 更新する</button>
                    <a href="{{ route('orders.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
