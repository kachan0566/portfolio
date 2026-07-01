@php
    $expectedDate = \App\Support\DemoData::expectedArrivalDate($purchase);
@endphp
<form method="POST" action="{{ route('purchases.patch-arrival', $purchase->id) }}" style="display:flex;flex-direction:column;gap:6px;min-width:140px;">
    @csrf
    @method('PATCH')
    @foreach ($search ?? [] as $key => $value)
        @if ($value !== '')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
    <input type="hidden" name="arrival_memo" value="{{ $purchase->arrival_memo ?? '' }}">
    <div>
        <label class="label" style="font-size:11px;margin-bottom:2px;">入荷予定日</label>
        <input class="input" type="date" name="expected_arrival_date" value="{{ old('expected_arrival_date', $expectedDate) }}" style="font-size:12px;padding:4px 8px;">
    </div>
    <button type="submit" class="btn btn-secondary btn-sm" style="align-self:flex-start;">保存</button>
</form>
