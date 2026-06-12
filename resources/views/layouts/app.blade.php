<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '受発注管理')</title>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f5f5f5; color: #333; }
        main { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
        h1 { font-size: 1.5rem; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
        th { background: #fafafa; font-weight: 600; }
        .empty { background: #fff; padding: 24px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <main>
        @yield('content')
    </main>
</body>
</html>
