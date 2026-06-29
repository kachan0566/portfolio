@extends('layouts.app')

@section('title', '長期在庫詳細')
@section('breadcrumb', '集計 / 在庫管理 / 長期在庫 / 詳細')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="code-cell">{{ $product->sku }}</h1>
            <p class="lead">入荷ロット別の長期在庫内訳（基準日: {{ $asOfDate }}）</p>
        </div>
        <a href="{{ route('inventory.index', ['tab' => 'long_term']) }}" class="btn btn-secondary">一覧に戻る</a>
    </div>

    <div class="kpi-grid" style="margin-bottom:16px;">
        <div class="kpi">
            <div class="kpi__label">現在庫</div>
            <div class="kpi__value" style="font-size:20px;">@include('partials.qty', ['qty' => $line->current_stock_qty, 'productId' => $product->id])</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">長期在庫</div>
            <div class="kpi__value" style="font-size:20px;">@include('partials.qty', ['qty' => $line->long_term_qty, 'productId' => $product->id])</div>
            <div class="kpi__sub">{{ number_format($line->long_term_value) }} 円</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">最古入荷日</div>
            <div class="kpi__value" style="font-size:18px;">{{ $line->oldest_received_date ?? '—' }}</div>
            <div class="kpi__sub">{{ $line->oldest_age_months !== null ? $line->oldest_age_months.'か月' : '' }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card__head"><h2 class="card__title">入荷ロット一覧</h2></div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>入荷日</th>
                            <th>入荷番号</th>
                            <th class="num">入荷数量</th>
                            <th class="num">残数量</th>
                            <th class="num">経過月数</th>
                            <th>長期在庫</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($line->lot_details as $lot)
                            <tr>
                                <td class="mono">{{ $lot->received_date }}</td>
                                <td class="code-cell">{{ $lot->receiving_code ?? '—' }}</td>
                                <td class="num mono">{{ number_format($lot->received_qty_m) }}m</td>
                                <td class="num mono">{{ number_format($lot->remaining_qty_m) }}m</td>
                                <td class="num mono">{{ $lot->age_months }}か月</td>
                                <td>
                                    @if ($lot->is_long_term)
                                        <span class="badge badge-amber">12か月以上</span>
                                    @else
                                        <span class="t-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
