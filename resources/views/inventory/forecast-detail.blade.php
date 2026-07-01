@extends('layouts.app')

@section('title', '月末予想詳細')
@section('breadcrumb', '集計 / 在庫管理 / 月末在庫予想 / 詳細')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="code-cell">{{ $product->sku }}</h1>
            <p class="lead">
                月末在庫予想の計算根拠（対象月: {{ $ym }} ／ 基準日: {{ $monthEndDate }}）
                @if ($forecastSummary->latest_snapshot)
                    <span class="badge badge-green badge--plain" style="margin-left:8px;">提出済 Ver.{{ $forecastSummary->latest_snapshot->version }}</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('inventory.index', ['tab' => 'forecast', 'ym' => $ym]) }}" class="btn btn-secondary">一覧に戻る</a>
            <a href="{{ route('sales.forecast.show', ['product' => $product->id, 'ym' => $ym]) }}" class="btn btn-secondary">売上見通しを編集</a>
        </div>
    </div>

    @include('inventory.partials.forecast-kpi-grid', [
        'summary' => $forecastSummary,
        'singleProduct' => true,
        'line' => $line,
    ])

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <div>
                <h2 class="card__title">入荷予定（製品発注）</h2>
                <p class="t-muted" style="font-size:12px;margin:4px 0 0;">売上見通しで入力した入荷数量を月末予想に反映しています。</p>
            </div>
        </div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>発注番号</th><th>入荷予定日</th><th class="num">発注数</th><th class="num">入荷済</th><th class="num">発注残</th><th class="num">入荷見通し</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($line->inbound_details as $po)
                            <tr>
                                <td class="code-cell"><a href="{{ route('purchases.show', $po->po_id) }}" class="link-strong">{{ $po->po_code }}</a></td>
                                <td class="mono">{{ $po->finish_date }}</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $po->qty_meters, 'productId' => $product->id])</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $po->received_qty, 'productId' => $product->id])</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $po->remaining_qty, 'productId' => $product->id])</td>
                                <td class="num mono t-strong">@include('partials.qty', ['qty' => $po->forecast_qty, 'productId' => $product->id])</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">対象の入荷予定はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <div>
                <h2 class="card__title">出荷見通し（月末予想の減算対象）</h2>
                <p class="t-muted" style="font-size:12px;margin:4px 0 0;">売上見通しで入力した出荷数量を月末予想に反映しています。</p>
            </div>
        </div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>受注番号</th><th>得意先</th><th>出荷予定日</th><th class="num">受注残</th><th class="num">出荷見通し</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($line->outbound_details as $order)
                            <tr>
                                <td class="code-cell"><a href="{{ route('orders.show', $order->order_id) }}" class="link-strong">{{ $order->order_code }}</a></td>
                                <td>{{ $order->customer }}</td>
                                <td class="mono">{{ $order->planned_ship_date }}</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $order->remaining_qty, 'productId' => $product->id])</td>
                                <td class="num mono t-strong">@include('partials.qty', ['qty' => $order->forecast_qty, 'productId' => $product->id])</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">対象の出荷見通しはありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <div>
                <h2 class="card__title">未出荷受注一覧</h2>
                <p class="t-muted" style="font-size:12px;margin:4px 0 0;">この品番の受注残です。出荷確定前の受注も含みます。</p>
            </div>
        </div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>受注番号</th>
                            <th>得意先</th>
                            <th class="num">受注数</th>
                            <th class="num">出荷済</th>
                            <th class="num">未出荷残</th>
                            <th>納期</th>
                            <th>出荷予定日メモ</th>
                            <th>ステータス</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unshippedOrders as $order)
                            <tr>
                                <td class="code-cell"><a href="{{ route('orders.show', $order->id) }}" class="link-strong">{{ $order->code }}</a></td>
                                <td>{{ $order->customer }}</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $order->qty, 'productId' => $product->id])</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $order->shipped, 'productId' => $product->id])</td>
                                <td class="num mono t-strong">@include('partials.qty', ['qty' => $order->remaining, 'productId' => $product->id])</td>
                                <td class="mono">{{ $order->due_date }}</td>
                                <td class="t-muted" style="font-size:12px;">{{ $order->ship_memo }}</td>
                                <td>@include('partials.status', ['status' => $order->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="empty">未出荷の受注はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
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
                                        <td class="num mono">@include('partials.qty', ['qty' => $lot->remaining_qty_m, 'productId' => $product->id])</td>
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
            <div class="card__head"><h2 class="card__title">手動調整</h2></div>
            <div class="card__body">
                @include('inventory.partials.forecast-adjustment-form', [
                    'targetYm' => $ym,
                    'productId' => $product->id,
                    'redirect' => 'detail',
                    'formId' => 'detail',
                ])
            </div>
            <div class="card__body card__body--flush" style="border-top:1px solid var(--border);">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>日時</th><th class="num">調整</th><th>理由</th><th>入力者</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($line->manual_adjustments as $adj)
                                <tr>
                                    <td class="mono t-muted" style="font-size:12px;">{{ \Illuminate\Support\Str::of($adj->updated_at)->replace('T', ' ')->before('+') }}</td>
                                    <td class="num mono">@include('partials.qty-display', ['qty' => abs($adj->adjustment_qty_m), 'productId' => $product->id, 'sign' => $adj->adjustment_qty_m >= 0 ? '+' : '-'])</td>
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
