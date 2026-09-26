#!/usr/bin/env bash
#
# verify.sh - 提出前のクリーン環境動作確認スクリプト
#
# 別ディレクトリにリポジトリをまっさらにクローンし、README の手順どおりに
# セットアップ → マイグレーション → テストが通るかを一気通貫で確認する。
# 今の作業ツリー（vendor/ や .env が既にある状態）は一切使わないため、
# 「別環境でプルしてきても動くか」を実際に近い形で検証できる。
#
# 使い方:
#   bash verify.sh                 # origin(GitLab) の main を検証
#   bash verify.sh <branch>        # 指定ブランチを検証
#   REPO_URL=... bash verify.sh    # クローン元を上書き
#   KEEP=1 bash verify.sh          # 検証後にクローンと起動コンテナを残す
#
set -euo pipefail

# ---- 設定（環境変数で上書き可能） ---------------------------------------
REPO_URL="${REPO_URL:-git@192.168.0.230:mimisbrunnr/certifylms.git}"
BRANCH="${1:-main}"
KEEP="${KEEP:-0}"
COMPOSER_IMAGE="${COMPOSER_IMAGE:-laravelsail/php83-composer:latest}"
WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/certifylms-verify.XXXXXX")"
CLONE_DIR="${WORKDIR}/certifylms"

# ---- ログ用ヘルパ --------------------------------------------------------
step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m[OK]\033[0m %s\n' "$*"; }
err()  { printf '\033[1;31m[NG]\033[0m %s\n' "$*" >&2; }

SAIL=""

cleanup() {
  local exit_code=$?
  if [[ "${KEEP}" == "1" ]]; then
    step "KEEP=1 のため後片付けをスキップ"
    echo "  クローン: ${CLONE_DIR}"
    [[ -n "${SAIL}" ]] && echo "  コンテナ停止は手動で: (cd ${CLONE_DIR} && ${SAIL} down -v)"
  else
    step "後片付け"
    if [[ -n "${SAIL}" && -d "${CLONE_DIR}" ]]; then
      (cd "${CLONE_DIR}" && ${SAIL} down -v >/dev/null 2>&1) || true
    fi
    # 通常削除を試み、root 所有ファイルが残って失敗したら Docker 経由で削除する
    if ! rm -rf "${WORKDIR}" 2>/dev/null; then
      docker run --rm -v "${WORKDIR}:/target" "${COMPOSER_IMAGE}" \
        rm -rf /target >/dev/null 2>&1 || true
      rm -rf "${WORKDIR}" 2>/dev/null || true
    fi
    if [[ -d "${WORKDIR}" ]]; then
      err "一時ディレクトリを削除しきれませんでした: ${WORKDIR}"
    else
      ok "一時ディレクトリを削除"
    fi
  fi
  if [[ ${exit_code} -eq 0 ]]; then
    printf '\n\033[1;32m======== 検証成功: 別環境でセットアップ〜テストまで通りました ========\033[0m\n'
  else
    printf '\n\033[1;31m======== 検証失敗 (exit=%s): 上のログを確認してください ========\033[0m\n' "${exit_code}" >&2
  fi
}
trap cleanup EXIT

# ---- 前提チェック --------------------------------------------------------
step "前提コマンドの確認 (git / docker)"
command -v git    >/dev/null 2>&1 || { err "git が見つかりません"; exit 1; }
command -v docker >/dev/null 2>&1 || { err "docker が見つかりません"; exit 1; }
docker info >/dev/null 2>&1 || { err "docker デーモンに接続できません（起動していますか？）"; exit 1; }
ok "git / docker OK"

# ---- クリーンクローン ----------------------------------------------------
step "クリーンクローン: ${REPO_URL} (${BRANCH})"
git clone --branch "${BRANCH}" --single-branch "${REPO_URL}" "${CLONE_DIR}"
cd "${CLONE_DIR}"
ok "クローン先: ${CLONE_DIR}"

# ---- 環境ファイル --------------------------------------------------------
step ".env の準備 (.env.example からコピー)"
if [[ ! -f .env.example ]]; then
  err ".env.example がリポジトリに含まれていません。別環境ではセットアップ不能です。"
  exit 1
fi
cp .env.example .env
ok ".env を作成"

# ---- vendor 生成（初回は sail がまだ無い） -------------------------------
# クリーンクローン直後は vendor/bin/sail が存在しないため、
# 先に公式 composer イメージで依存を入れて sail を使えるようにする。
step "依存パッケージの初期インストール (composer)"
# --user で実行ユーザーの UID/GID を渡し、生成物が root 所有にならないようにする。
# (root 所有だと後片付けの rm が Permission denied になる)
docker run --rm \
  --user "$(id -u):$(id -g)" \
  -e COMPOSER_HOME=/tmp/composer \
  -v "${CLONE_DIR}:/var/www/html" \
  -w /var/www/html \
  "${COMPOSER_IMAGE}" \
  composer install --no-interaction --prefer-dist --ignore-platform-reqs
[[ -x ./vendor/bin/sail ]] || { err "composer install 後も vendor/bin/sail がありません"; exit 1; }
SAIL="./vendor/bin/sail"
ok "vendor/ を生成し sail を利用可能に"

# ---- Sail 起動 -----------------------------------------------------------
step "Sail コンテナ起動"
${SAIL} up -d
ok "コンテナ起動"

# DB が受け付けられるようになるまで待機
step "DB の起動待ち"
for i in $(seq 1 30); do
  if ${SAIL} exec -T mysql mysqladmin ping -h localhost --silent >/dev/null 2>&1; then
    ok "MySQL 応答あり"
    break
  fi
  if [[ "${i}" == "30" ]]; then
    err "MySQL が起動しませんでした（タイムアウト）"
    exit 1
  fi
  sleep 2
done

# ---- アプリセットアップ --------------------------------------------------
step "npm install"
${SAIL} npm install

step "アプリケーションキー生成"
${SAIL} artisan key:generate

step "マイグレーション + シーディング"
${SAIL} artisan migrate --seed --force

step "アセットビルド"
${SAIL} npm run build

# ---- テスト --------------------------------------------------------------
step "テスト実行 (PHPUnit)"
${SAIL} artisan test

ok "全ステップ完了"
