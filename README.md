# portfolio

就活用ポートフォリオ

Laravel で作成した Web アプリケーションです。

## セットアップ

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

ブラウザで http://127.0.0.1:8000 を開いてください。

## 技術スタック

- PHP / Laravel
- SQLite（開発用）
