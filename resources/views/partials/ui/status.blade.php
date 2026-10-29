@php
    // ステータス文字列に応じたバッジの色を決める共通部品。
    $map = [
        '未出荷'   => 'badge-gray',
        '一部出荷' => 'badge-amber',
        '出荷済み' => 'badge-green',
        '未入荷'   => 'badge-gray',
        '一部入荷' => 'badge-amber',
        '入荷済み' => 'badge-green',
    ];
    $class = $map[$status] ?? 'badge-gray';
@endphp
<span class="badge {{ $class }}">{{ $status }}</span>
