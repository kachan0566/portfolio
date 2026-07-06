<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ダッシュボード') ｜ 受発注・在庫・売上管理</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="layout">
    @php
        $nav = [
            ['section' => 'メイン'],
            ['route' => 'dashboard',        'label' => 'ダッシュボード', 'icon' => 'grid'],
            ['section' => 'マスタ管理'],
            ['route' => 'products.index',   'label' => '商品管理',     'icon' => 'box'],
            ['route' => 'prices.index',     'label' => '月別糸価格', 'icon' => 'tag'],
            ['route' => 'recipes.index',    'label' => '商品レシピ',   'icon' => 'beaker'],
            ['route' => 'customers.index',  'label' => '得意先',       'icon' => 'users'],
            ['route' => 'suppliers.index',  'label' => '仕入先',       'icon' => 'truck'],
            ['section' => '取引'],
            ['route' => 'orders.index',     'label' => '受注管理',     'icon' => 'cart'],
            ['route' => 'purchases.index',  'label' => '発注管理',     'icon' => 'clipboard'],
            ['route' => 'receivings.index', 'label' => '入荷処理',     'icon' => 'arrow-down'],
            ['route' => 'shipments.index',  'label' => '出荷処理',     'icon' => 'arrow-up'],
            ['section' => '集計'],
            ['route' => 'inventory.index',  'label' => '在庫管理',     'icon' => 'archive'],
            ['route' => 'sales.index',      'label' => '売上・粗利',   'icon' => 'chart'],
        ];
    @endphp
    <aside class="sidebar">
        <div class="sidebar__brand">
            <div class="sidebar__logo">繊</div>
            <div>
                <div class="sidebar__title">繊維生産管理</div>
                <div class="sidebar__subtitle">受発注・在庫・売上</div>
            </div>
        </div>
        <nav class="sidebar__nav">
            @foreach ($nav as $item)
                @if (isset($item['section']))
                    <div class="sidebar__section">{{ $item['section'] }}</div>
                @else
                    <a href="{{ route($item['route']) }}"
                       class="nav-link {{ request()->routeIs(str_replace('.index', '', $item['route']) . '*') ? 'is-active' : '' }}">
                        <span class="nav-link__icon">@include('partials.icon', ['name' => $item['icon']])</span>
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="sidebar__footer">
            <div class="avatar">木</div>
            <div>
                <div class="sidebar__user-name">木村 克哉</div>
                <div class="sidebar__user-role">生産管理担当</div>
            </div>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div>
                <div class="topbar__title">@yield('title', 'ダッシュボード')</div>
                <div class="breadcrumb">@yield('breadcrumb', 'ホーム')</div>
            </div>
            <div class="topbar__right">
                <span class="topbar__date">2026年6月15日（月）</span>
                <span class="pill-month">対象月: 2026-06</span>
            </div>
        </header>

        <main class="content">
            @if (session('success'))
                <div class="alert alert-success">
                    @include('partials.icon', ['name' => 'check'])
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">
                    @include('partials.icon', ['name' => 'alert'])
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
