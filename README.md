# PinkClub-RakuToys

楽天市場のアダルトグッズを検索・紹介する楽天アフィリエイト対応サイト用CMSです。[PinkClub-FL](https://github.com/TaniyanR/PinkClub-FL) を基盤に、商品取得部分を楽天商品検索APIへ置き換えています。

## 対象

- 楽天市場で販売されているアダルトグッズ
- 楽天商品検索APIで「アダルトグッズ」を検索して取得できる商品
- 楽天アフィリエイトIDを利用した成果対象リンク

本リポジトリは成人向け商品を扱います。公開時は法令、楽天の規約、広告掲載基準、年齢確認要件を確認してください。

## 主な機能

- 楽天商品検索API（2026-07-01版）による商品取得
- 商品名、価格、説明、レビュー、画像、ショップ、アフィリエイトURLの保存
- 商品一覧、検索、詳細、ランキング、タグ、関連商品
- ショップ・楽天ジャンルからの商品導線
- WordPress風の管理画面
- API認証情報の保存と10件テスト取得
- cronによる自動取得
- 初回セットアップ、DBマイグレーション、API履歴、同期ログ
- SEO、OGP、JSON-LD、サイトマップ、RSS、アクセス解析

## 使用API

[楽天商品検索API](https://webservice.rakuten.co.jp/documentation/ichiba-item-search) version 2026-07-01

```text
https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20260701
```

必要な認証情報は次のとおりです。

- 楽天アプリID（必須）
- アクセスキー（必須）
- 楽天アフィリエイトID（アフィリエイトURLを取得する場合に指定）

アプリIDとアクセスキーは管理画面または環境変数で設定します。認証情報をGitへコミットしないでください。

対応する環境変数:

```text
RAKUTEN_APPLICATION_ID
RAKUTEN_ACCESS_KEY
RAKUTEN_AFFILIATE_ID
```

## API取得条件

初期値は次のとおりです。

- keyword: `アダルトグッズ`
- format: `json`
- formatVersion: `2`
- availability: `1`
- imageFlag: `1`
- hits: 最大30
- page: 1〜100

検索キーワードは管理設定で変更できます。短時間に同じURLへ大量アクセスすると制限される可能性があるため、APIレスポンスを10分間再利用し、cronから段階的に取得します。

## 必要環境

- PHP 8.1以上
- PHP cURL、PDO MySQL、mbstring、JSON
- MySQL 8.0またはMariaDB 10.5以上
- Apacheまたはnginx
- cron（自動取得を使う場合）

XAMPPでも動作確認できます。

## セットアップ

1. ファイル一式をサーバーへ配置します。
2. `/public/setup_check.php` を開きます。
3. DBホスト、ポート、DB名、ユーザー名、パスワードを保存します。
4. セットアップを実行します。
5. `/public/login0718.php` から管理画面へログインします。
6. 初期管理者パスワードを変更します。
7. 「商品情報API設定」で楽天アプリID、アクセスキー、楽天アフィリエイトIDを保存します。
8. 「10件テスト取得」でAPI接続とDB保存を確認します。

初期管理者は `admin` / `password` です。公開前に必ず変更してください。

## 自動取得

公開アクセスではAPI同期を実行しません。次のコマンドをcronから10分間隔で実行してください。

```bash
php /path/to/PinkClub-RakuToys/scripts/auto_import.php
```

楽天APIは1リクエスト最大30件、最大100ページです。次回ページはDBに保存され、cron実行ごとに続きから取得します。

## 主要URL

- 公開トップ: `/public/`
- 管理ログイン: `/public/login0718.php`
- 管理トップ: `/admin/index.php`
- セットアップ確認: `/public/setup_check.php`
- 商品API設定: `/admin/api_items.php`
- 自動取得設定: `/admin/api_auto.php`
- API・同期ログ: `/admin/sync_logs.php`

## セキュリティ

- `config.local.php`、API認証情報、DBパスワード、ログを公開しないでください。
- 管理者パスワードを変更し、HTTPSで運用してください。
- アクセスキーはAPIリクエストヘッダーへ設定し、ログURLには出力しません。
- 外部URLは楽天APIから返却されたURLを使用します。

## クレジット

楽天Web Serviceの指定クレジットをフッターに表示します。

<!-- Rakuten Web Services Attribution Snippet FROM HERE -->
<a href="https://webservice.rakuten.co.jp/" target="_blank"><img src="https://webservice.rakuten.co.jp/img/credit/200709/credit_22121.gif" border="0" alt="Rakuten Web Service Center" title="Rakuten Web Service Center" width="221" height="21"/></a>
<!-- Rakuten Web Services Attribution Snippet TO HERE -->

クレジット表示仕様: [How to Display Branding & Give Credit](https://webservice.rakuten.co.jp/guide/credit)
