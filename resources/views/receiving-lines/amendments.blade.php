@extends('layouts.app')

@section('title', '反明細変更履歴')
@section('breadcrumb', '取引 / 入荷処理 / 変更履歴')

@section('content')
    <div class="page-header">
        <div>
            <h1>反明細変更履歴</h1>
            <p class="lead">
                入荷 <span class="code-cell">{{ $receiving->code }}</span>
                ／ 明細行 {{ $line->line_no }}
                ／ {{ $sku }}
            </p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('receiving-lines.show', $line->id) }}" class="btn btn-secondary">
                @include('partials.icon', ['name' => 'back']) 反修正に戻る
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">変更履歴（{{ $amendments->count() }} 件）</h2>
        </div>
        <div class="card__body card__body--flush">
            @if ($amendments->isEmpty())
                <p class="t-muted" style="padding:16px;margin:0;">変更履歴はまだありません。</p>
            @else
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>日時</th>
                                <th>反番号</th>
                                <th>項目</th>
                                <th class="num">変更前</th>
                                <th class="num">変更後</th>
                                <th class="num">明細合計（前）</th>
                                <th class="num">明細合計（後）</th>
                                <th>理由</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($amendments as $amendment)
                                <tr>
                                    <td class="mono t-muted" style="font-size:12px;">
                                        {{ $amendment->changed_at?->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="code-cell mono">{{ $amendment->roll_code }}</td>
                                    <td>{{ \App\Models\ReceivingRollAmendment::fieldLabel($amendment->field) }}</td>
                                    <td class="num mono">{{ number_format((float) $amendment->old_value, 3) }}</td>
                                    <td class="num mono">{{ number_format((float) $amendment->new_value, 3) }}</td>
                                    <td class="num mono t-muted">
                                        {{ \App\Support\QtyHelper::formatTanCount((float) $amendment->line_qty_tan_before) }}反
                                        ／ {{ number_format((int) $amendment->line_qty_m_before) }}m
                                    </td>
                                    <td class="num mono">
                                        @if ($amendment->line_qty_tan_after !== null)
                                            {{ \App\Support\QtyHelper::formatTanCount((float) $amendment->line_qty_tan_after) }}反
                                            ／ {{ number_format((int) $amendment->line_qty_m_after) }}m
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="t-muted" style="font-size:13px;">{{ $amendment->reason ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
