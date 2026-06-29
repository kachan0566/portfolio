@extends('layouts.app')

@section('title', '出荷予定登録')
@section('breadcrumb', '取引 / 受注管理 / 出荷予定登録')

@section('content')
    <div class="page-header">
        <div>
            <h1>出荷予定（出荷確定）登録</h1>
            <p class="lead">{{ $order->code }} ／ {{ $order->customer }} ／ 受注残 @include('partials.qty', ['qty' => $remaining, 'productId' => $order->product_id])</p>
        </div>
        <a href="{{ route('orders.show', $order->id) }}" class="btn btn-secondary">受注詳細に戻る</a>
    </div>

    @if ($existing->isNotEmpty())
        <div class="card" style="margin-bottom:16px;">
            <div class="card__head"><h2 class="card__title">登録済みの出荷予定</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>予定番号</th><th>出荷予定日</th><th class="num">確定数量</th><th class="num">未出荷</th><th>状態</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($existing as $plan)
                                <tr>
                                    <td class="code-cell">{{ $plan->code }}</td>
                                    <td class="mono">{{ $plan->planned_ship_date }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $plan->confirmed_qty_m, 'productId' => $order->product_id])</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $plan->unshipped_qty_m, 'productId' => $order->product_id])</td>
                                    <td>{{ $plan->status_label }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card form-card">
        <div class="card__body">
            <form action="{{ route('shipment-plans.store', $order->id) }}" method="POST">
                @csrf
                <div class="form-row">
                    <div class="field">
                        <label class="label" for="planned_ship_date">出荷予定日<span class="req">*</span></label>
                        <input class="input" type="date" id="planned_ship_date" name="planned_ship_date"
                               value="{{ old('planned_ship_date', $order->planned_ship_date ?? $order->due_date) }}" required>
                    </div>
                    <div class="field">
                        <label class="label" for="confirmed_qty_m">出荷確定数量（m）<span class="req">*</span></label>
                        <input class="input" type="number" id="confirmed_qty_m" name="confirmed_qty_m" min="0.01" step="0.01"
                               value="{{ old('confirmed_qty_m', $remaining) }}" required>
                        <p class="field-hint">受注残の範囲内で入力してください。</p>
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="note">備考</label>
                    <textarea class="textarea" id="note" name="note" rows="2">{{ old('note') }}</textarea>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">登録する</button>
                    <a href="{{ route('orders.show', $order->id) }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
