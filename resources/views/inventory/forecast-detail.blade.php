@extends('layouts.app')

@section('title', '月末予想詳細')
@section('breadcrumb', '集計 / 在庫管理 / 月末在庫予想 / 詳細')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="code-cell">{{ $product->sku }}</h1>
            <p class="lead">月末在庫予想の計算根拠（対象月: {{ $ym }} ／ 基準日: {{ $monthEndDate }}）</p>
        </div>
        <a href="{{ route('inventory.index', ['tab' => 'forecast', 'ym' => $ym]) }}" class="btn btn-secondary">一覧に戻る</a>
    </div>

    <div class="kpi-grid" style="margin-bottom:16px;">
        <div class="kpi">
            <div class="kpi__label">現在庫</div>
            <div class="kpi__value" style="font-size:20px;">@include('partials.qty', ['qty' => $line->current_stock_qty, 'productId' => $product->id])</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">自動予想</div>
            <div class="kpi__value" style="font-size:20px;">@include('partials.qty', ['qty' => $line->auto_forecast_qty, 'productId' => $product->id])</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">手動調整</div>
            <div class="kpi__value" style="font-size:20px;">{{ $line->manual_adjustment_qty >= 0 ? '+' : '' }}{{ number_format($line->manual_adjustment_qty) }}m</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">月末予想</div>
            <div class="kpi__value" style="font-size:20px;">@include('partials.qty', ['qty' => $line->forecast_qty, 'productId' => $product->id])</div>
            <div class="kpi__sub">{{ number_format($line->forecast_value) }} 円</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:16px;">
        <div class="card">
            <div class="card__head"><h2 class="card__title">入荷予定（製品発注）</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>発注番号</th><th>上がり予定</th><th class="num">発注数</th><th class="num">入荷済</th><th class="num">未入荷</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($line->inbound_details as $po)
                                <tr>
                                    <td class="code-cell"><a href="{{ route('purchases.show', $po->po_id) }}" class="link-strong">{{ $po->po_code }}</a></td>
                                    <td class="mono">{{ $po->finish_date }}</td>
                                    <td class="num mono">{{ number_format($po->qty_meters) }}m</td>
                                    <td class="num mono">{{ number_format($po->received_qty) }}m</td>
                                    <td class="num mono t-strong">{{ number_format($po->remaining_qty) }}m</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty">対象の入荷予定はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">出荷確定済み明細</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>予定番号</th><th>受注</th><th>出荷予定日</th><th class="num">確定</th><th class="num">未出荷</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($line->outbound_details as $plan)
                                <tr>
                                    <td class="code-cell">{{ $plan->code }}</td>
                                    <td><a href="{{ route('orders.show', $plan->order_id) }}" class="link-strong">{{ $plan->order_code }}</a></td>
                                    <td class="mono">{{ $plan->planned_ship_date }}</td>
                                    <td class="num mono">{{ number_format($plan->confirmed_qty_m) }}m</td>
                                    <td class="num mono">{{ number_format($plan->unshipped_qty_m) }}m</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty">対象の出荷確定はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card__head"><h2 class="card__title">入荷ロット別残数量（月末シミュレーション後）</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>入荷日</th><th>入荷番号</th><th class="num">残数量</th><th>区分</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($line->lot_details as $lot)
                                @if ($lot->remaining_qty_m > 0)
                                    <tr>
                                        <td class="mono">{{ $lot->received_date }}</td>
                                        <td class="code-cell">{{ $lot->receiving_code ?? '—' }}</td>
                                        <td class="num mono">{{ number_format($lot->remaining_qty_m) }}m</td>
                                        <td class="t-muted" style="font-size:12px;">{{ $lot->source_type }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">手動調整履歴</h2></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>日時</th><th class="num">調整</th><th>理由</th><th>入力者</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($line->manual_adjustments as $adj)
                                <tr>
                                    <td class="mono t-muted" style="font-size:12px;">{{ \Illuminate\Support\Str::of($adj->updated_at)->replace('T', ' ')->before('+') }}</td>
                                    <td class="num mono">{{ $adj->adjustment_qty_m >= 0 ? '+' : '' }}{{ number_format($adj->adjustment_qty_m) }}m</td>
                                    <td>{{ $adj->reason }}</td>
                                    <td class="t-muted">{{ $adj->created_by }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty">手動調整はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
