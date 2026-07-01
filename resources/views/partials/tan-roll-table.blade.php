@php
    $rolls = $rolls ?? collect();
    $isGreige = $isGreige ?? false;
    $productId = $productId ?? null;
    $greigeSku = $greigeSku ?? null;
    $showWeaving = $showWeaving ?? true;
    $showDyeing = $showDyeing ?? true;
@endphp
@if ($rolls->isEmpty())
    <p class="t-muted" style="margin:0;font-size:13px;">反明細はまだありません。</p>
@else
    <div class="table-wrap">
        <table class="data data--compact">
            <thead>
                <tr>
                    <th>反ID</th>
                    @if ($showWeaving)
                        <th class="num">織り上がり（m）</th>
                    @endif
                    @if ($showDyeing)
                        <th class="num">染め上がり（m）</th>
                    @endif
                    <th class="num">誤差</th>
                    <th>計測日</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rolls as $roll)
                    @php
                        $variance = \App\Support\FabricTanRoll::varianceMeters($roll);
                        $nominal = (int) ($roll->nominal_meters ?? 0);
                    @endphp
                    <tr>
                        <td class="code-cell mono">{{ $roll->code }}</td>
                        @if ($showWeaving)
                            <td class="num mono">
                                @if ($roll->weaving_meters > 0)
                                    {{ number_format((float) $roll->weaving_meters, 1) }}
                                    <span class="t-muted" style="font-size:11px;">／標準{{ $nominal }}m</span>
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        @if ($showDyeing)
                            <td class="num mono">
                                @if ($roll->dyeing_meters !== null)
                                    {{ number_format((float) $roll->dyeing_meters, 1) }}
                                    <span class="t-muted" style="font-size:11px;">／標準{{ $nominal }}m</span>
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        <td class="num mono {{ abs($variance) >= 0.05 ? 't-amber' : 't-muted' }}">
                            {{ \App\Support\QtyHelper::formatVariance($variance) }}
                        </td>
                        <td class="mono t-muted" style="font-size:12px;">
                            {{ $roll->dyeing_measured_at ?? $roll->weaving_measured_at ?? $roll->measured_at }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
