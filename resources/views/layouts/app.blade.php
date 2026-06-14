<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '受発注管理')</title>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f5f5f5; color: #333; }
        main { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
        h1 { font-size: 1.5rem; margin: 0; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; vertical-align: middle; }
        th { background: #fafafa; font-weight: 600; }
        .empty { background: #fff; padding: 24px; border: 1px solid #ddd; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px; }
        .form-card { background: #fff; border: 1px solid #ddd; padding: 24px; }
        .field { margin-bottom: 16px; }
        .label { display: block; margin-bottom: 6px; font-weight: 600; }
        .input { width: 100%; max-width: 400px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
        .input:focus { outline: 2px solid #2563eb; border-color: #2563eb; }
        .error { color: #b91c1c; font-size: 0.875rem; margin: 6px 0 0; }
        .success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 12px 16px; margin-bottom: 16px; }
        .actions { display: flex; gap: 8px; margin-top: 24px; }
        .table-actions { display: flex; gap: 8px; align-items: center; }
        .inline-form { display: inline; margin: 0; }
        .btn { display: inline-block; padding: 8px 14px; border-radius: 4px; font-size: 0.875rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; line-height: 1.4; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #fff; color: #333; border-color: #ccc; }
        .btn-secondary:hover { background: #f9fafb; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-sm { padding: 6px 10px; font-size: 0.8125rem; }
    </style>
</head>
<body>
    <main>
        @yield('content')
    </main>
</body>
</html>
