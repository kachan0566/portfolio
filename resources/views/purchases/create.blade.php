@extends('layouts.app')

@section('title', '発注登録')
@section('breadcrumb', '取引 / 発注管理 / 登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>発注登録</h1>
            <p class="lead">仕入先への新しい発注を登録します。</p>
        </div>
        <a href="{{ route('purchases.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    @if ($sourceOrder)
        <div class="alert alert-info" style="margin-bottom:16px;">
            受注 <strong class="code-cell">{{ $sourceOrder->code }}</strong>（{{ $sourceOrder->customer }}）から発注を作成します。
            <a href="{{ route('orders.show', $sourceOrder->id) }}" class="link-strong" style="margin-left:8px;">受注詳細に戻る</a>
        </div>
    @endif

    <div class="card form-card">
        <div class="card__body">
            <form action="{{ route('purchases.store') }}" method="POST">
                @csrf
                <div class="field">
                    <label class="label" for="order_code">受注番号（任意）</label>
                    <input class="input" type="text" id="order_code" name="order_code"
                           value="{{ $sourceOrder?->code }}"
                           placeholder="SO-2606-001（空欄のまま登録も可能）"
                           style="max-width:320px;">
                    @if ($sourceOrder)
                        <input type="hidden" name="order_id" value="{{ $sourceOrder->id }}">
                    @endif
                    <p class="field-hint">受注との紐づけは任意です。空欄のまま登録するとフリー発注として扱われます。</p>
                </div>
                <div class="field">
                    <label class="label" for="customer">得意先<span class="req">*</span></label>
                    <select class="select" id="customer" name="customer_id">
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected($sourceOrder && $c->name === $sourceOrder->customer)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="supplier">仕入先<span class="req">*</span></label>
                    <select class="select" id="supplier" name="supplier_id">
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="product">品番<span class="req">*</span></label>
                        <select class="select" id="product" name="product_id">
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected($sourceOrder && $p->id === $sourceOrder->product_id)>{{ $p->sku }}（{{ $p->color }}）</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="qty">数量<span class="req">*</span></label>
                        <input class="input" type="number" id="qty" name="qty" min="1"
                               value="{{ $suggestedQty ?? ($sourceOrder ? max(0, $sourceOrder->qty - $sourceOrder->shipped) : '') }}"
                               placeholder="200">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="order_date">発注日<span class="req">*</span></label>
                        <input class="input" type="date" id="order_date" name="order_date" value="2026-06-15">
                    </div>
                    <div class="field">
                        <label class="label" for="eta">入荷予定日<span class="req">*</span></label>
                        <input class="input" type="date" id="eta" name="eta"
                               value="{{ $sourceOrder?->due_date }}">
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 登録する</button>
                    <a href="{{ route('purchases.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
