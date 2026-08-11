# PinkClub-RakuToys

楽天市場APIを利用した、アダルトグッズ専門の比較・検索アフィリエイトサイトです。

## 方針

- PHP + MySQL/MariaDB + JavaScript
- フレームワーク不使用
- 楽天市場商品検索API 2026-07-01版を利用
- Application ID / Access Key / Affiliate IDはGitに保存しない
- 商品はDBへ保存し、公開側はDB検索を基本とする
- キーワード・価格帯・レビュー評価・送料無料・在庫・並び順で検索
- 商品詳細から楽天アフィリエイトURLへ遷移
- cronで商品情報を定期同期
- SEO向けに商品詳細ページを個別URL化

## セットアップ

1. `config/config.example.php` を `config/config.php` にコピーします。
2. DB接続情報と楽天API情報を設定します。
3. `database/schema.sql` をMySQL/MariaDBへ適用します。
4. `scripts/import.php` をCLIで実行して商品を取得します。
5. Webルートを `public/` に設定します。

```bash
php scripts/import.php
```

## 楽天API設定

必要な値:

- Application ID
- Access Key
- Affiliate ID

取得キーワードは `config/config.php` の `rakuten.keywords` で複数指定できます。

例:

```php
'keywords' => [
    'アダルトグッズ',
    'ローター',
    'バイブ',
    '吸引',
    'ラブグッズ',
],
```

## 公開URL

- `/` トップ・検索
- `/search.php` 商品検索
- `/item.php?id=123` 商品詳細

## 管理URL

- `/admin/` API設定確認

## 開発方針

- 既存PinkClubシリーズと同様、軽量・安全・SEO重視
- APIキーやDBパスワードはコミットしない
- 作業ブランチ + Draft PRで進める
