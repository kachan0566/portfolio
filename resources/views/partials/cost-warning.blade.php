@if (!empty($warnings))
    <div class="alert alert-danger" style="margin-bottom:16px;">
        @include('partials.icon', ['name' => 'alert'])
        <div>
            <strong>{{ $heading ?? '製造コストを算出できない項目があります。' }}</strong>
            <ul style="margin:8px 0 0;padding-left:18px;">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
