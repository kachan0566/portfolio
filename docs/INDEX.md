# ドキュメント一覧

`memo/`・`markdown/`・`milestone/` を **`docs/`** に集約しました（2026-10 Step 5）。

作業再開メモだけ **`memo/restart.md`** に残しています（更新は手動）。

## 構成

| パス | 内容 |
|------|------|
| [`requirements.md`](requirements.md) | 要件定義 |
| [`portfolio.md`](portfolio.md) | ポートフォリオ・面接向け説明 |
| [`qty-tan-spec.md`](qty-tan-spec.md) | 反数・数量仕様 |
| [`change-instructions.md`](change-instructions.md) | 初期AI向け変更指示（参照用） |
| [`form.md`](form.md) / [`page.md`](page.md) | 画面・フォームメモ |
| [`db/`](db/) | DB設計（`DBplan.md` 地図 / `DB.md` 列定義） |
| [`api/`](api/) | API仕様メモ |
| [`integration/`](integration/) | 工場連携（将来） |
| [`views/`](views/) | Blade・ルート整理メモ |
| [`plan/`](plan/) | マイルストーン・応募計画 |
| [`interview/`](interview/) | 面接準備（Git管理外・ローカルのみ） |

## コード整理（Step 1〜4）後のパス例

- Blade 部品: `resources/views/partials/ui/` など
- Support: `App\Support\{Domain}\*`（例: `App\Support\Qty\QtyHelper`）
- Models: `App\Models\{Domain}\*`（例: `App\Models\Master\Product`）

---

以下は Laravel プロジェクト標準の README です。
