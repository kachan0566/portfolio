@extends('layouts.app')

@section('title', '入荷登録')
@section('breadcrumb', '取引 / 入荷処理 / 登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>入荷登録</h1>
            <p class="lead">入荷対象の発注を選び、入荷数量を入力します。</p>
        </div>
        <a href="{{ route('receivings.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card__head">
                <h2 class="card__title">入荷待ちの発注</h2>
            </div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>発注番号</th><th>仕入先</th><th>品番</th><th class="num">残数</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $po)
                                @php $rem = \App\Support\DemoState::poRemaining($po->id); @endphp
                                <tr>
                                    <td class="code-cell">{{ $po->code }}</td>
                                    <td>{{ $po->supplier }}</td>
                                    <td class="code-cell t-strong">{{ $po->sku }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $rem, 'productId' => $po->product_id])</td>
                                    <td><span class="badge badge-indigo badge--plain">{{ $po->stage }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card form-card" style="max-width:none;">
            <div class="card__head"><h2 class="card__title">入荷内容</h2></div>
            <div class="card__body">
                <form action="{{ route('receivings.store') }}" method="POST">
                    @csrf
                    <div class="field">
                        <label class="label" for="po">対象発注<span class="req">*</span></label>
                        <select class="select" id="po" name="po_id">
                            @foreach ($pending as $po)
                                @php $rem = \App\Support\DemoState::poRemaining($po->id); @endphp
                                <option value="{{ $po->id }}">{{ $po->code }} ／ {{ $po->sku }}（残 {{ $rem }}）</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label class="label" for="qty">入荷数量<span class="req">*</span></label>
                            <input class="input" type="number" id="qty" name="qty" min="1" placeholder="100">
                        </div>
                        <div class="field">
                            <label class="label" for="date">入荷日<span class="req">*</span></label>
                            <input class="input" type="date" id="date" name="date" value="2026-06-15">
                        </div>
                    </div>
                    <p class="field-hint">登録すると対象品番の在庫が入荷数量だけ増加します。</p>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 入荷を登録</button>
                        <a href="{{ route('receivings.index') }}" class="btn btn-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
