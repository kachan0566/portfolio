@php
    $expectedDate = \App\Support\DemoData::expectedArrivalDate($purchase);
@endphp
<form method="POST" action="{{ route('purchases.patch-arrival', $purchase->id) }}" style="display:flex;flex-direction:column;gap:6px;min-width:180px;">
    @csrf
    @method('PATCH')
    @foreach ($search ?? [] as $key => $value)
        @if ($value !== '')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
    <input type="hidden" name="expected_arrival_date" value="{{ $expectedDate }}">
    <div>
        <label class="label" style="font-size:11px;margin-bottom:2px;">メモ</label>
        <textarea class="textarea" name="arrival_memo" rows="2" placeholder="例）染工場から連絡あり" style="font-size:12px;min-height:48px;">{{ old('arrival_memo', $purchase->arrival_memo ?? '') }}</textarea>
    </div>
    <button type="submit" class="btn btn-secondary btn-sm" style="align-self:flex-start;">保存</button>
</form>
