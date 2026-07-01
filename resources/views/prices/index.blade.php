@extends('layouts.app')

@section('title', '月別原材料価格')
@section('breadcrumb', 'マスタ管理 / 月別原材料価格')

@section('content')
    <div class="page-header">
        <div>
            <h1>月別原材料価格</h1>
            <p class="lead">原材料ごとの年月別単価を確認します。製造コストの計算根拠になります。</p>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <h2 class="card__title">検索</h2>
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'sku' => ['label' => '品番', 'placeholder' => '原材料品番・名称'],
            ],
        ])
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <h2 class="card__title">単価マトリクス（円 / 単位）</h2>
        </div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>品番</th>
                            <th>原材料</th>
                            <th>単位</th>
                            @foreach ($months as $m)
                                <th class="num">{{ $m }}</th>
                            @endforeach
                            <th class="num">前月比</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrix as $material => $byMonth)
                            @php
                                $unit = $byMonth->first()->unit;
                                $last = $byMonth->get($months->last())?->price ?? 0;
                                $prev = $byMonth->get($months[$months->count() - 2] ?? null)?->price ?? $last;
                                $diff = $last - $prev;
                            @endphp
                            <tr>
                                <td class="code-cell">{{ $byMonth->first()->material_sku }}</td>
                                <td class="t-strong">{{ $material }}</td>
                                <td class="t-muted">{{ $unit }}</td>
                                @foreach ($months as $m)
                                    <td class="num mono">
                                        @if ($byMonth->has($m))
                                            {{ number_format($byMonth->get($m)->price) }}
                                        @else
                                            <span class="t-muted">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="num mono">
                                    @if ($diff > 0)
                                        <span class="kpi__trend up">▲ +{{ number_format($diff) }}</span>
                                    @elseif ($diff < 0)
                                        <span class="kpi__trend down">▼ {{ number_format($diff) }}</span>
                                    @else
                                        <span class="t-muted">±0</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">登録履歴（{{ $prices->count() }} 件）</h2>
        </div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>品番</th><th>原材料</th><th>年月</th><th class="num">単価</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($prices->sortByDesc('ym') as $p)
                            <tr>
                                <td class="code-cell">{{ $p->material_sku }}</td>
                                <td class="t-strong">{{ $p->material }}</td>
                                <td class="mono">{{ $p->ym }}</td>
                                <td class="num mono">{{ number_format($p->price) }} 円/{{ $p->unit }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
