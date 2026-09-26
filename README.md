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

## 外部API連携の状態

### 現状: モックで完結

本プロジェクトの外部 API 連携（Gemini / Google Calendar / Stripe）は、**開発・テストをモックで完結**させています。

- アプリ本体には実 API 連携コードを実装済み（Gemini・Google は Laravel HTTP クライアント経由、Stripe は公式 SDK 経由）。
- **テストは実 API を叩きません**。`Http::fake()` によるレスポンス差し替え、または対象サービスをスタブに差し替えることで外部通信・署名検証を遮断しています。
- そのため、**API キーが未設定でもセットアップ・`sail artisan test` は通ります**。クリーン環境での動作確認（`verify.sh`）もキー不要です。

### 未設定時の挙動

| 機能 | チケット | キー未設定時の挙動 |
|------|---------|-------------------|
| AI相談（Gemini） | S-A-02 | 送信時に `status=error` を返す。画面・ルート自体は動作 |
| カレンダー連携（Google Calendar） | S-A-01 | 連携をスキップし従来の空き判定にフォールバック。**面談機能自体は動作** |
| 追加面談購入（Stripe） | S-A-03 | Checkout セッション作成に失敗。**購入導線のみ利用不可** |

### 実運用で有効化する場合

各サービスの認証情報を `.env` に設定すれば、モックではなく実 API に接続します（コード変更は不要）。設定キーは `config/services.php` / `config/ai-chat.php` に定義済みです。

```env
# Gemini AI（S-A-02）
# https://aistudio.google.com/ で API キーを発行
GEMINI_API_KEY=your-key
# 任意（既定: gemini-2.5-flash / v1beta エンドポイント）
# GEMINI_MODEL=
# GEMINI_ENDPOINT=

# Google Calendar（S-A-01）
# Google Cloud Console で OAuth 2.0 クライアントを作成し、
# 承認済みリダイレクト URI に GOOGLE_REDIRECT_URI と同じ値を登録する
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://<your-host>/settings/google-calendar/callback

# Stripe（S-A-03）
# Stripe ダッシュボードでシークレットキーを取得（開発は sk_test_... を推奨）
STRIPE_SECRET=sk_test_...
# Webhook エンドポイント登録時に発行される署名シークレット
STRIPE_WEBHOOK_SECRET=whsec_...
```

> 各機能の詳細仕様は `.infos/src/S-A-01`〜`S-A-03` を参照。
> Google のコールバックは `settings/google-calendar/callback`（コーチ専用ルート `settings.google-calendar.callback`）。Cloud Console の承認済みリダイレクト URI にはホストを含めた同一 URL を登録すること。

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
