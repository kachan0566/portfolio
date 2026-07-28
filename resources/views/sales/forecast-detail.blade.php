@extends('layouts.app')

@section('title', '売上見通し詳細')
@section('breadcrumb', '集計 / 売上・粗利 / 見通し / 詳細')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="code-cell">{{ $product->sku }}</h1>
            <p class="lead">
                売上・出荷見通しの入力（対象月: {{ $ym }} ／ 出荷予定日が {{ $monthEndDate }} までを今月計上）
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('sales.index', ['tab' => 'forecast', 'ym' => $ym]) }}" class="btn btn-secondary">一覧に戻る</a>
            <a href="{{ route('inventory.forecast.show', ['product' => $product->id, 'ym' => $ym]) }}" class="btn btn-secondary">月末在庫予想を見る</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">
            @include('partials.icon', ['name' => 'check'])
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="kpi-grid" style="margin-bottom:16px;">
        <div class="kpi">
            <div class="kpi__label">出荷実績</div>
            <div class="kpi__value" style="font-size:18px;">@include('partials.qty', ['qty' => $detail->actual_qty, 'productId' => $product->id])</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">残り見通し出荷</div>
            <div class="kpi__value" style="font-size:18px;">@include('partials.qty', ['qty' => $detail->forecast_remaining_qty, 'productId' => $product->id])</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">月末見込み出荷</div>
            <div class="kpi__value" style="font-size:18px;">@include('partials.qty', ['qty' => $detail->total_qty, 'productId' => $product->id])</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">見通し売上（残り）</div>
            <div class="kpi__value" style="font-size:18px;">{{ number_format($detail->forecast_remaining_sales) }}<span style="font-size:13px;"> 円</span></div>
        </div>
        <div class="kpi">
            <div class="kpi__label">現在庫</div>
            <div class="kpi__value" style="font-size:18px;">@include('partials.qty', ['qty' => $detail->current_stock_qty, 'productId' => $product->id])</div>
        </div>
    </div>

    @php $impact = $inventoryImpact; @endphp
    <div class="card" style="margin-bottom:16px;">
        <div class="card__head"><h2 class="card__title">月末在庫予想への影響</h2></div>
        <div class="card__body">
            <div class="kpi-grid">
                <div class="kpi">
                    <div class="kpi__label">入荷見通し</div>
                    <div class="kpi__value" style="font-size:18px;">+@include('partials.qty', ['qty' => $impact->inbound_qty, 'productId' => $product->id])</div>
                </div>
                <div class="kpi">
                    <div class="kpi__label">出荷見通し</div>
                    <div class="kpi__value" style="font-size:18px;">-@include('partials.qty', ['qty' => $impact->outbound_qty, 'productId' => $product->id])</div>
                </div>
                <div class="kpi">
                    <div class="kpi__label">手動調整</div>
                    <div class="kpi__value" style="font-size:18px;">
                        {{ $impact->manual_adjustment_qty >= 0 ? '+' : '' }}@include('partials.qty', ['qty' => abs($impact->manual_adjustment_qty), 'productId' => $product->id])
                    </div>
                    <div class="kpi__sub t-muted">在庫予想画面で別途登録</div>
                </div>
                <div class="kpi">
                    <div class="kpi__label">月末予想在庫</div>
                    <div class="kpi__value" style="font-size:18px;">@include('partials.qty', ['qty' => $impact->forecast_qty, 'productId' => $product->id])</div>
                    <div class="kpi__sub t-muted">現在庫 @include('partials.qty', ['qty' => $impact->current_stock_qty, 'productId' => $product->id])</div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('sales.forecast.store', $product->id) }}" id="forecast-form">
        @csrf
        <input type="hidden" name="target_ym" value="{{ $ym }}">

        <div class="card" style="margin-bottom:16px;">
            <div class="card__head">
                <div>
                    <h2 class="card__title">今月計上の見込み</h2>
                    <p class="t-muted" style="font-size:12px;margin:4px 0 0;">引当済みの発注と受注を並べて、今月の入荷・出荷見通しを入力します。</p>
                </div>
            </div>
            <div class="card__body card__body--flush">
                @forelse ($detail->pairs as $pair)
                    <div class="forecast-pair" style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid var(--border);">
                        {{-- 発注側 --}}
                        <div style="padding:16px;border-right:1px solid var(--border);{{ $pair->inbound_is_saved ? 'background:#fffbeb;' : '' }}">
                            <div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:8px;">発注</div>
                            @if ($pair->po)
                                <div class="code-cell" style="margin-bottom:6px;">
                                    <a href="{{ route('purchases.show', $pair->po_id) }}" class="link-strong">{{ $pair->po_code }}</a>
                                </div>
                                <div style="font-size:12px;margin-bottom:4px;">
                                    <span class="t-muted">入荷予定日:</span>
                                    <span class="mono t-strong">{{ $pair->arrival_date ?: '—' }}</span>
                                </div>
                                <div class="t-muted" style="font-size:12px;margin-bottom:4px;">{{ $pair->po->supplier ?? '' }}</div>
                                <div class="t-muted" style="font-size:12px;margin-bottom:8px;">
                                    発注 @include('partials.qty', ['qty' => $pair->po->qty_meters ?? 0, 'productId' => $product->id])
                                    ／ 入荷済 @include('partials.qty', ['qty' => \App\Models\PurchaseOrder::receivedQtyFor((int) $pair->po_id), 'productId' => $product->id])
                                    ／ 残 @include('partials.qty', ['qty' => $pair->po_remaining_qty, 'productId' => $product->id])
                                </div>
                                <div class="field" style="max-width:200px;">
                                    <label class="label" style="font-size:11px;">今月入荷見通し（反）</label>
                                    @include('partials.qty-input', [
                                        'name' => 'inbound_'.$pair->po_id,
                                        'valueMeters' => $pair->effective_inbound_qty,
                                        'productId' => $product->id,
                                        'metersPerTan' => $product->meters_per_tan,
                                        'pageKey' => 'sales-forecast-detail',
                                        'compact' => true,
                                    ])
                                    <p class="field-hint" style="font-size:11px;">デフォルト: @include('partials.qty', ['qty' => $pair->default_inbound_qty, 'productId' => $product->id])（発注残）</p>
                                </div>
                            @else
                                <span class="t-muted" style="font-size:12px;">紐づく発注なし</span>
                            @endif
                        </div>

                        {{-- 受注側 --}}
                        <div style="padding:16px;{{ $pair->outbound_is_saved ? 'background:#fffbeb;' : '' }}">
                            <div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:8px;">受注</div>
                            @if ($pair->order)
                                <div class="code-cell" style="margin-bottom:6px;">
                                    <a href="{{ route('orders.show', $pair->order_id) }}" class="link-strong">{{ $pair->order_code }}</a>
                                </div>
                                <div style="font-size:12px;margin-bottom:4px;">
                                    <span class="t-muted">得意先:</span> {{ $pair->order->customer }}
                                </div>
                                <div style="font-size:12px;margin-bottom:4px;">
                                    <span class="t-muted">出荷予定日:</span>
                                    <span class="mono">{{ $pair->planned_ship_date }}</span>
                                    <span class="t-muted">／ 納期:</span>
                                    <span class="mono">{{ $pair->order->due_date }}</span>
                                </div>
                                <div class="t-muted" style="font-size:12px;margin-bottom:8px;">
                                    受注 @include('partials.qty', ['qty' => $pair->order->qty, 'productId' => $product->id])
                                    ／ 出荷済 @include('partials.qty', ['qty' => $pair->order->shipped, 'productId' => $product->id])
                                    ／ 残 @include('partials.qty', ['qty' => $pair->order_remaining_qty, 'productId' => $product->id])
                                </div>
                                <div class="field" style="max-width:200px;">
                                    <label class="label" style="font-size:11px;">今月出荷見通し（反）</label>
                                    @include('partials.qty-input', [
                                        'name' => 'outbound_'.$pair->order_id,
                                        'valueMeters' => $pair->effective_outbound_qty,
                                        'productId' => $product->id,
                                        'metersPerTan' => $product->meters_per_tan,
                                        'pageKey' => 'sales-forecast-detail',
                                        'compact' => true,
                                    ])
                                    <p class="field-hint" style="font-size:11px;">デフォルト: @include('partials.qty', ['qty' => $pair->default_outbound_qty, 'productId' => $product->id])（{{ $pair->default_outbound_source }}）</p>
                                </div>
                            @else
                                <span class="t-muted" style="font-size:12px;">紐づく受注なし</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty" style="padding:24px;">今月計上対象の発注・受注はありません。</div>
                @endforelse
            </div>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <button type="submit" class="btn btn-primary">この品番の見通しを保存</button>
        </div>
    </form>

    <form method="POST" action="{{ route('sales.forecast.reset', $product->id) }}" onsubmit="return confirm('デフォルト値に戻しますか？');" style="display:inline;">
        @csrf
        <input type="hidden" name="ym" value="{{ $ym }}">
        <button type="submit" class="btn btn-secondary">デフォルトに戻す</button>
    </form>

    @if ($detail->future_count > 0)
        <details class="card" style="margin-top:16px;">
            <summary class="card__head" style="cursor:pointer;list-style:none;">
                <h2 class="card__title" style="display:inline;">
                    参考：来月以降の受注・発注
                    <span class="badge badge-indigo badge--plain" style="margin-left:8px;font-size:11px;">{{ $detail->future_count }} 件</span>
                </h2>
                <p class="t-muted" style="font-size:12px;margin:4px 0 0;">出荷予定日が {{ $monthEndDate }} より後のもの。今月の見通しには含みません。</p>
            </summary>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>種別</th>
                                <th>番号</th>
                                <th>相手先</th>
                                <th class="num">残数量</th>
                                <th>予定日</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detail->future_orders as $order)
                                <tr>
                                    <td><span class="badge badge-indigo badge--plain">受注</span></td>
                                    <td class="code-cell"><a href="{{ route('orders.show', $order->id) }}" class="link-strong">{{ $order->code }}</a></td>
                                    <td>{{ $order->customer }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => $order->remaining, 'productId' => $product->id])</td>
                                    <td class="mono">{{ $order->planned_ship_date }}</td>
                                </tr>
                            @endforeach
                            @foreach ($detail->future_purchase_orders as $po)
                                <tr>
                                    <td><span class="badge badge-amber badge--plain">発注</span></td>
                                    <td class="code-cell"><a href="{{ route('purchases.show', $po->id) }}" class="link-strong">{{ $po->code }}</a></td>
                                    <td>{{ $po->supplier }}</td>
                                    <td class="num mono">@include('partials.qty', ['qty' => \App\Models\PurchaseOrder::remainingQtyFor((int) $po->id, $po), 'productId' => $product->id])</td>
                                    <td class="mono">{{ \App\Support\DemoData::expectedArrivalDate($po) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    @endif
@endsection

@push('scripts')
    @include('partials.qty-unit-loader')
    <script>
        QtyUnit.initPage('sales-forecast-detail');
    </script>
@endpush
