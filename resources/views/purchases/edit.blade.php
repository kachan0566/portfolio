@extends('layouts.app')

@section('title', '発注編集')
@section('breadcrumb', '取引 / 発注管理 / 編集')

@section('content')
    <div class="page-header">
        <div>
            <h1>発注編集</h1>
            <p class="lead">{{ $purchase->code }}（{{ $purchase->customer }}）を編集します。</p>
        </div>
        <a href="{{ route('purchases.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="card form-card" style="max-width:none;">
        <div class="card__body">
            <form action="{{ route('purchases.update', $purchase->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="customer">得意先<span class="req">*</span></label>
                        <select class="select" id="customer" name="customer_id">
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" @selected($c->name === $purchase->customer)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="supplier">仕入先<span class="req">*</span></label>
                        <select class="select" id="supplier" name="supplier_id">
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" @selected($s->name === $purchase->supplier)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="product">品番<span class="req">*</span></label>
                        <select class="select" id="product" name="product_id">
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected($p->sku === $purchase->sku)>{{ $p->sku }}（{{ $p->color }}）</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="qty">数量<span class="req">*</span></label>
                        <input class="input" type="number" id="qty" name="qty" min="1" value="{{ $purchase->qty }}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="order_date">発注日<span class="req">*</span></label>
                        <input class="input" type="date" id="order_date" name="order_date" value="{{ $purchase->order_date }}">
                    </div>
                    <div class="field">
                        <label class="label" for="eta">入荷予定日（納期）<span class="req">*</span></label>
                        <input class="input" type="date" id="eta" name="eta" value="{{ $purchase->eta }}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="finish_date">上がり予定日</label>
                        <input class="input" type="date" id="finish_date" name="finish_date" value="{{ $purchase->finish_date }}">
                    </div>
                    <div class="field">
                        <label class="label" for="contact_date">先方連絡予定日</label>
                        <input class="input" type="date" id="contact_date" name="contact_date" value="{{ $purchase->contact_date }}">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="stage">進捗段階</label>
                    <select class="select" id="stage" name="stage" style="max-width:320px;">
                        @foreach (\App\Support\DemoData::PO_STAGES as $st)
                            <option value="{{ $st }}" @selected($st === $purchase->stage)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label">各工程の予定日</label>
                    @foreach (\App\Support\DemoData::PO_STAGES as $st)
                        <div class="form-row" style="align-items:center;margin-bottom:8px;">
                            <span style="font-size:13px;min-width:140px;">{{ $st }}</span>
                            <input class="input" type="date" name="schedule[{{ $st }}]" value="{{ $purchase->schedule[$st] ?? '' }}">
                        </div>
                    @endforeach
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 更新する</button>
                    <a href="{{ route('purchases.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
