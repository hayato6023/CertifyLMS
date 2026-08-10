# Certify LMS

マルチ資格対応の資格学習プラットフォーム。受講生・コーチ・管理者の3ロールによる招待制サービス。

---

## 技術スタック

- PHP / Laravel
- MySQL
- Laravel Sail (Docker)
- Laravel Sanctum（API認証）
- PHPUnit（テスト）
- Stripe（決済連携）
- Google Calendar API（カレンダー連携）
- Google Gemini API（AI相談）

> バージョン詳細は `composer.json` / `package.json` を参照。

---

## 環境構築

```bash
# 1. リポジトリのクローン
git clone <repository-url>
cd CertifyLMS

# 2. 環境ファイルの準備
cp .env.example .env

# 3. Sailの起動
./vendor/bin/sail up -d

# 4. 依存パッケージのインストール
./vendor/bin/sail composer install
./vendor/bin/sail npm install

# 5. アプリケーションキーの生成
./vendor/bin/sail artisan key:generate

# 6. マイグレーション + シーディング
./vendor/bin/sail artisan migrate --seed

# 7. アセットのビルド
./vendor/bin/sail npm run build
```

> 詳細な手順は環境に応じて調整してください。

---

## よく使うコマンド

| コマンド | 説明 |
|---------|------|
| `sail up -d` | コンテナ起動 |
| `sail down` | コンテナ停止 |
| `sail artisan test` | テスト実行 |
| `sail artisan migrate` | マイグレーション |
| `sail artisan db:seed` | シーディング |
| `sail artisan route:list` | ルート一覧 |
| `sail npm run dev` | アセット開発ビルド |

---

## ブランチ・PR運用

- デフォルトブランチ: `main`
- 作業ブランチ: `feature/{チケットID}`（例: `feature/S-B-01`）
- 1チケット = 1 PR = 1ブランチ
- PRはセルフレビュー後にセルフマージ

---

## 外部API設定

Advance フェーズで必要な環境変数:

```env
# Google Calendar（S-A-01）
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=

# Gemini AI（S-A-02）
GEMINI_API_KEY=

# Stripe（S-A-03）
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

---

## ドキュメント構成

| パス | 内容 |
|------|------|
| `docs/requirements.md` | 要件定義書 |
| `docs/design.md` | 設計書 |
| `docs/wbs-spec.md` | WBS設計仕様 |
| `docs/wbs.xlsx` | WBS（手動管理） |
| `docs/traceability-spec.md` | トレーサビリティ設計仕様 |
| `docs/traceability.xlsx` | トレーサビリティマトリクス（手動管理） |
| `.infos/*.md` | チケット詳細仕様（全41件） |
| `.kiro/steering/*.md` | Kiro向け実装ガイド |

---

## ロール

| ロール | 説明 |
|--------|------|
| 受講生（student） | 資格学習を行うエンドユーザー |
| コーチ（coach） | 受講生を担当し学習支援を行う |
| 管理者（admin） | プラットフォーム全体の運営管理 |
