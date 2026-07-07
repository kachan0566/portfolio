あなたのプロジェクト（約68個のBladeファイル）を見たうえで、**目的の補完**と**このコードベース向けの学習ロードマップ**をまとめます。

---

## 持つべき目的（あなたの3つ＋補完）

### あなたが挙げた目的（そのまま重要）


| 目的                      | このプロジェクトでの具体例                                               |
| ----------------------- | ----------------------------------------------------------- |
| Bladeの書き方を理解する          | `@extends`, `@section`, `@include`, `@if`, `@foreach` など    |
| 各ファイルの役割                | `layouts/` = 共通枠、`orders/index` = 一覧ページ、`partials/qty` = 部品 |
| 共通化を学び、さらに共通化できる部分を見つける | `partials/icon`, `partials/list-search` など                  |


### 追加で持つとよい目的

1. **画面がどう表示されるかの流れを追える**
  - 例：ブラウザ → ルート → コントローラー → `return view(...)` → Blade → HTML
  - Bladeだけ見ても「データがどこから来るか」が分からないので、コントローラーとの関係もセットで学ぶと理解が早いです。
2. **「表示」と「処理」の分け方を判断できる**
  - このプロジェクトでは `QtyHelper::format()` や `ListSearch::isActive()` のように、**PHPクラスに処理を寄せ、Bladeは表示だけ**にしている箇所があります。
  - 共通化のとき「部品（partial）にするか」「PHPクラスにするか」を選べるようになるのが目標です。
3. **安全な出力のルールを理解する**
  - `{{ $変数 }}` … エスケープあり（基本はこれ）
  - `{!! $html !!}` … エスケープなし（`icon.blade.php` のSVGなど、信頼できるHTMLだけ）
4. **フォームまわりの定型パターン**
  - `old('sku')`（入力エラー時の値の復元）
  - `@csrf`（送信の安全対策）
  - `@selected(...)`（選択状態の保持）
5. **「共通化しすぎない」判断力**
  - 2回出たから即共通化、ではなく「3回目・変更が一緒に起きそうか」で判断する感覚も大事です。

---



## このプロジェクトのBlade構造（地図）

```
resources/views/
├── layouts/
│   └── app.blade.php      ← 全画面の共通枠（サイドバー・ヘッダー・アラート）
├── dashboard.blade.php    ← 各機能の「ページ本体」
├── orders/
│   ├── index.blade.php    ← 一覧
│   ├── create.blade.php   ← 登録
│   ├── edit.blade.php     ← 編集
│   └── show.blade.php     ← 詳細
├── partials/              ← 全体で使う共通部品
│   ├── icon.blade.php
│   ├── qty.blade.php
│   ├── list-search.blade.php
│   └── ...
├── sales/partials/        ← 売上画面だけで使う部品
└── inventory/partials/    ← 在庫画面だけで使う部品
```

**1ページの典型的な形**（ほぼ全ページがこれ）:

```1:6:resources/views/orders/index.blade.php
@extends('layouts.app')

@section('title', '受注管理')
@section('breadcrumb', '取引 / 受注管理')

@section('content')
```

**レイアウト側**が穴をあけて、各ページが中身を差し込むイメージです:

```6:7:resources/views/layouts/app.blade.php
    <title>@yield('title', 'ダッシュボード') ｜ 受発注・在庫・売上管理</title>
```

```88:93:resources/views/layouts/app.blade.php
            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
```

---



## 効率的な学習ロードマップ（6週間イメージ）



### 第1段階：文法と仕組み（2〜3日）

**やること**

- Laravel公式のBlade基礎（継承・セクション・include・ディレクティブ）をざっと読む
- このプロジェクトで実際に使っているものだけに絞る

**このプロジェクトで使っている主な記法**


| 記法                    | 意味             | 例                  |
| --------------------- | -------------- | ------------------ |
| `@extends`            | 共通レイアウトを使う     | `layouts.app`      |
| `@section` / `@yield` | ページごとのタイトル・本文  | `title`, `content` |
| `@include`            | 部品を読み込む        | `partials.icon`    |
| `@if` / `@foreach`    | 条件分岐・繰り返し      | ナビ、テーブル行           |
| `@push` / `@stack`    | ページ固有のJSを末尾に足す | `orders/create`    |
| `@php`                | 短い準備処理         | `qty.blade.php`    |
| `{{ }}`               | 変数を安全に表示       | `{{ $o->qty }}`    |


**小さな確認課題**

- `dashboard.blade.php` を開き、「どこがレイアウト由来で、どこがこのページ固有か」を色分けしてみる

---



### 第2段階：レイアウト1本を完全理解（1日）

**読むファイル（この順番）**

1. `layouts/app.blade.php` … 全体の骨格
2. `dashboard.blade.php` … シンプルなページ例
3. `products/create.blade.php` … フォーム例

**理解ポイント**

- サイドバーのナビはレイアウト内の `@foreach`
- 成功・エラーメッセージもレイアウトで共通表示
- 各ページは `page-header` → `card` → `table` / `form` の組み合わせ

---



### 第3段階：共通部品（partials）を深掘り（3〜4日）

**優先度の高い部品（使われ方が典型的）**


| 部品                               | 役割       | 学ぶこと                     |
| -------------------------------- | -------- | ------------------------ |
| `partials/icon.blade.php`        | SVGアイコン  | パラメータ `name` の渡し方        |
| `partials/qty.blade.php`         | 数量表示     | デフォルト値 `??`、PHPクラスへの委譲   |
| `partials/status.blade.php`      | ステータスバッジ | マップ配列で色分け                |
| `partials/list-search.blade.php` | 検索フォーム   | 設定配列 `fields` で画面ごとに出し分け |


`qty.blade.php` は「部品のお手本」です:

```1:8:resources/views/partials/qty.blade.php
@php
    $qty = $qty ?? 0;
    $productId = $productId ?? null;
    $isGreige = $isGreige ?? false;
    $greigeSku = $greigeSku ?? null;
    $prefix = $prefix ?? '';
@endphp
{{ $prefix }}{{ \App\Support\QtyHelper::format($qty, $productId, $isGreige, $greigeSku) }}
```

**小さな確認課題**

- `orders/index.blade.php` で `@include('partials.qty', [...])` が何箇所あるか数える
- 各箇所で渡しているパラメータの違いをメモする

---



### 第4段階：1機能を端から端まで追う（2日）

**おすすめ：受注管理（orders）**

1. `routes/web.php` … URLとコントローラーの対応
2. `OrderController.php` … `view('orders.index', [...])` で何を渡しているか
3. `orders/index.blade.php` … 受け取った変数の使い方
4. `orders/create.blade.php` … フォーム＋`@push('scripts')`

ここまで追えると、「Bladeはコントローラーから渡されたデータをHTMLにする場所」と腹落ちします。

---



### 第5段階：共通化の「見つけ方」訓練（3〜5日）

**すでに共通化されているパターン**

- 数量表示 → `partials/qty` 系（`qty-input`, `qty-display`, `qty-aggregate`）
- 検索バー → `partials/list-search`
- アイコン → `partials/icon`
- レシピ関連 → `partials/recipe-*`
- タブの中身 → `sales/partials/*`, `inventory/partials/*`

**まだ共通化できそうな候補（練習用）**


| 候補                      | 根拠                                                  | 難易度 |
| ----------------------- | --------------------------------------------------- | --- |
| `page-header` ブロック      | 37ファイルで同じHTML構造                                     | ★☆☆ |
| KPIカード（`kpi-grid` 内の1枚） | dashboard / sales / inventory で似た構造                 | ★★☆ |
| タブUI                    | `sales/index` と `inventory/index` でインラインスタイル付きの似たタブ | ★★☆ |
| 登録・編集フォームの「一覧に戻る＋送信ボタン」 | create/edit 系で繰り返し                                  | ★☆☆ |
| 成功アラート                  | `layouts/app` にあるのに `sales/index` でも重複              | ★☆☆ |


**共通化を見つけるチェックリスト（自分用）**

1. 同じHTML構造が **3回以上** 出てくるか？
2. 変わるのは **タイトル・文言・リンク先** だけか？
3. 1箇所直したとき、他も **同じ修正が必要** になりそうか？
4. 部品にしたとき、渡すパラメータは **5個以内** に収まるか？

4つとも「はい」なら共通化の候補です。

---



### 第6段階：自分で共通化を設計する（1週間）

**段階的な練習（実装はAgentモードで）**

1. **観察だけ** … `page-header` のバリエーションを一覧表にする（一覧用 / 登録用 / 詳細用）
2. **設計** … `partials/page-header.blade.php` に渡すパラメータ案を紙に書く
  - 例: `title`, `lead`, `actionLabel`, `actionUrl`, `actionIcon`
3. **1ファイルだけ置き換え** … 例: `customers/create.blade.php` だけ新部品を使う
4. **他ファイルへ展開** … 問題なければ他の create 系へ

最初から全部を共通化しようとしないのがコツです。

---



## 1日90分の効率的な学習ルーティン

```
[15分] 前日読んだファイルをもう一度開き、言葉で説明する
[30分] 新しいファイルを1〜2個、上から下まで読む（コントローラーもセット）
[20分] 「繰り返し」「部品化」「PHPクラス化」のメモを3行書く
[15分] 小さな確認（ブラウザで画面を見て、どのBladeが効いているか当てる）
[10分] 翌日読むファイルを決める
```

---



## 学習の進捗チェック（自分に問う）

- [x] 新しいページを作るとき、`@extends` → `@section` の順で書ける
- [x] `@include('partials.xxx', ['key' => $value])` の意味を説明できる
- [x] コントローラーの `return view(...)` とBladeの変数名が対応していると分かる
- [ ] `partials/` と `sales/partials/` の使い分けが説明できる
- [ ] 似たHTMLを見つけたら「部品化候補か？」と判断できる
- [ ] Bladeに複雑な計算を書くより、PHPクラスに移す判断ができる

---



## おすすめの学習順（ファイル）

1. `layouts/app.blade.php`
2. `partials/icon.blade.php` → `partials/qty.blade.php` → `partials/status.blade.php`
3. `partials/list-search.blade.php`
4. `orders/index.blade.php`（一覧のお手本）
5. `products/create.blade.php`（フォームのお手本）
6. `sales/index.blade.php`（タブ＋部品の組み合わせ）
7. `dashboard.blade.php`（KPI・グラフの複合例）

---



## まとめ

あなたの3つの目的はそのまま軸にしてよく、足すなら **「コントローラーとのデータの流れ」「表示と処理の分離」「共通化の判断基準」** が効果的です。

このプロジェクトはすでに `@include` ベースの共通化がよくできているので、次のステップは **「page-header や KPIカードなど、まだコピペされているUIパターンを自分で部品化する練習」** が一番伸びます。

特定の段階（例：第3段階の partials 解説、第4段階の orders 追跡）を深掘りしたい場合は、番号を指定してもらえれば、そのファイルを読みながら一緒に解説できます。実際に部品化まで進めたいときは Agent モードに切り替えてもらえれば対応できます。

第3段階を「合格ライン」として考えるなら、**はい、その4つを中心に勉強する**、という理解で大丈夫です。

ただし、「この4つ以外は一切見なくていい」という意味ではなく、**共通部品（partial）の典型的な使い方を学ぶための、優先して読むファイル**という位置づけです。









## 第3段階で何をする段階か

第3段階のタイトルは「共通部品（partials）を深掘り（3〜4日）」です。  
ここでのゴールは、**`@include` で部品を呼び出す仕組み**と、**部品ごとに違う設計パターン**を理解することです。

そのために、プロジェクト内の partial の中から「よく使われていて、学びやすい」4つが選ばれています。

| ファイル | 学ぶパターン |
|---|---|
| `icon.blade.php` | パラメータを1つ渡すシンプルな部品 |
| `qty.blade.php` | デフォルト値（`??`）と PHPクラスへの処理の委譲 |
| `status.blade.php` | 配列マップで見た目を切り替える |
| `list-search.blade.php` | 設定配列で画面ごとに出し分ける |

つまり4つ全部を読む理由は、**部品の「型」が4種類ある**からです。1つだけだと partial の全体像がつかみにくい、という設計になっています。

## 4つ全部「必須」か？

**第3段階としては、4つとも読むことを想定しています。**  
ただし優先順位はあります。

1. **まず `qty.blade.php`** … 「部品のお手本」と明記されているので、ここが核心
2. **`icon.blade.php`** … いちばんシンプルな入り口
3. **`status.blade.php`**
4. **`list-search.blade.php`** … いちばん複雑

ドキュメント末尾の「おすすめの学習順」でも、

> `icon` → `qty` → `status` → `list-search`

の順が書かれています。

1日90分のルーティンでは「新しいファイルを1〜2個読む」とあるので、**3〜4日かけて4つを順番に読む**イメージです。

## 4つ以外は勉強しなくていい？

**第3段階の時点では、まずこの4つに集中してよい**です。

他にも partial はあります（`qty-input`、`qty-display`、`recipe-*` など）。これらは第5段階で「すでに共通化されているパターン」として触れる想定です。第3段階で全部を読む必要はありません。

また、確認課題では `orders/index.blade.php` で `@include('partials.qty', [...])` を追うよう書かれています。これは4つの部品ファイルを読んだあと、**実際の画面でどう使われているか**を確認するための補助です。

## まとめ

- **第3段階の主役は、その4つの partial ファイル**
- **4つとも読む想定**（ただし `qty` がいちばん重要）
- **他の partial は第3段階では後回しでOK**
- 読み終えたら `orders/index.blade.php` で `@include` の使われ方を確認する

「4つ全部を完璧に暗記する」より、「部品にパラメータを渡して、画面の一部を共通化している」という感覚をつかむことが第3段階の目的です。

特定の1ファイル（例：`qty.blade.php` だけ）を一緒に読み解きたい場合は、番号やファイル名を指定してもらえれば解説できます。