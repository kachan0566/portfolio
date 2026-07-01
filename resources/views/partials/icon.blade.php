@php
    // 受け取った name に応じて SVG アイコンを出力する共通部品。
    $name = $name ?? 'grid';
    $icons = [
        'grid'       => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        'box'        => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'layers'     => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>',
        'tag'        => '<path d="M3 7v5l8 8 6-6-8-8H3z"/><circle cx="7" cy="11" r="1.4"/>',
        'beaker'     => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 18l-5-9V3"/><path d="M7 15h10"/>',
        'users'      => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 4.5a3 3 0 0 1 0 6M21 20c0-2.6-1.5-4.8-3.6-5.6"/>',
        'truck'      => '<path d="M3 6h11v9H3z"/><path d="M14 9h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
        'cart'       => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h2.5l2.2 12h11l2-8H6"/>',
        'clipboard'  => '<rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 9h6M9 13h6M9 17h3"/>',
        'arrow-down' => '<path d="M12 4v13"/><path d="m6 11 6 6 6-6"/><path d="M5 21h14"/>',
        'arrow-up'   => '<path d="M12 20V7"/><path d="m6 13 6-6 6 6"/><path d="M5 3h14"/>',
        'archive'    => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/><path d="M10 12h4"/>',
        'chart'      => '<path d="M4 4v16h16"/><rect x="7" y="11" width="3" height="6"/><rect x="12" y="7" width="3" height="10"/><rect x="17" y="13" width="3" height="4"/>',
        'plus'       => '<path d="M12 5v14M5 12h14"/>',
        'edit'       => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash'      => '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>',
        'back'       => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
        'check'      => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'search'     => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'download'   => '<path d="M12 4v11"/><path d="m7 10 5 5 5-5"/><path d="M4 19h16"/>',
        'alert'      => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'yen'        => '<path d="M7 4l5 7 5-7"/><path d="M12 11v9"/><path d="M8 14h8M8 17h8"/>',
        'package'    => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'close'      => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    ];
    $path = $icons[$name] ?? $icons['grid'];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="100%" height="100%">{!! $path !!}</svg>
