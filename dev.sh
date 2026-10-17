#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# 本机试用（源码目录直接跑，不写 systemd、不复制到 /opt）
#
#   bash dev.sh                 # 装依赖/建库/起服务 → http://127.0.0.1:8099
#   bash dev.sh --port 8080     # 换端口
#   bash dev.sh --no-serve      # 只准备环境，不起服务
#   bash dev.sh --fresh         # 删掉本地 sqlite 重来（演示数据会重建）
#
# 正式部署用 ./install.sh（复制到 /opt、写 systemd、可 MySQL）。
# ---------------------------------------------------------------------------
set -euo pipefail

cd "$(dirname -- "${BASH_SOURCE[0]}")"

PORT=8099
SERVE=1
FRESH=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --port) PORT="${2:?--port 需要值}"; shift 2 ;;
    --port=*) PORT="${1#*=}"; shift ;;
    --no-serve) SERVE=0; shift ;;
    --fresh) FRESH=1; shift ;;
    -h|--help) awk 'NR>2 { if ($0 !~ /^#/) exit; sub(/^# ?/, ""); print }' "$0"; exit 0 ;;
    *) echo "[FAIL] 未知参数: $1" >&2; exit 1 ;;
  esac
done

info() { printf '[INFO] %s\n' "$*"; }
ok()   { printf '[ OK ] %s\n' "$*"; }
warn() { printf '[WARN] %s\n' "$*"; }
die()  { printf '[FAIL] %s\n' "$*" >&2; exit 1; }

# ------------------------------------------------------------------ PHP
command -v php >/dev/null 2>&1 || {
  for c in php8.4 php8.3 php8.2; do command -v "$c" >/dev/null 2>&1 && export PATH="$(dirname "$(command -v "$c")"):$PATH"; done
}
PHP_BIN="$(command -v php || true)"
[[ -n "$PHP_BIN" ]] || die "找不到 php（apt install -y php8.2-cli php8.2-mbstring php8.2-xml php8.2-intl php8.2-bcmath php8.2-zip php8.2-sqlite3）"
PHP_BIN="$(readlink -f "$PHP_BIN")"
info "PHP $("$PHP_BIN" -r 'echo PHP_VERSION;') → $PHP_BIN"

for ext in mbstring xml intl bcmath zip pdo_sqlite; do
  "$PHP_BIN" -r "exit(extension_loaded('$ext') ? 0 : 1);" || {
    pkg="$ext"
    [[ "$ext" == "pdo_sqlite" ]] && pkg="sqlite3"
    [[ "$ext" == "pdo_mysql" ]] && pkg="mysql"
    die "缺少 PHP 扩展: ${ext}（Debian/Ubuntu: apt install -y php8.2-${pkg}）"
  }
done
ok "PHP 扩展齐了"

# ------------------------------------------------------------------ composer
# 不能假设 composer 在 PATH 里：很多机器装到 /usr/local/bin 却不在非登录 shell 的 PATH，
# 或者只有 composer.phar + php。逐个候选用 PHP 显式跑一次 --version 验证。
export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1

COMPOSER_CMD=()
composer_version() { "$@" --version 2>/dev/null | grep -m1 'Composer version' || true; }

try_composer() {
  local candidate
  for candidate in "$(command -v composer 2>/dev/null || true)" \
                   /usr/local/bin/composer /usr/bin/composer \
                   /usr/local/bin/composer.phar /usr/bin/composer.phar; do
    [[ -n "$candidate" && -f "$candidate" ]] || continue
    if [[ -n "$(composer_version "$PHP_BIN" "$candidate")" ]]; then
      COMPOSER_CMD=("$PHP_BIN" "$candidate"); return 0
    fi
    if [[ -x "$candidate" ]] && [[ -n "$(composer_version "$candidate")" ]]; then
      COMPOSER_CMD=("$candidate"); return 0
    fi
  done
  return 1
}

if ! try_composer; then
  warn "没找到可用的 composer，下载到项目内 ./composer.phar（sha384 校验）"
  tmp="$(mktemp -d)"
  curl -fsS https://getcomposer.org/installer -o "$tmp/installer" || { rm -rf "$tmp"; die "下载 composer installer 失败"; }
  expected="$(curl -fsS https://composer.github.io/installer.sig || true)"
  actual="$("$PHP_BIN" -r "echo hash_file('sha384', '${tmp}/installer');")"
  [[ -n "$expected" && "$expected" == "$actual" ]] || { rm -rf "$tmp"; die "installer 签名校验失败"; }
  "$PHP_BIN" "$tmp/installer" --install-dir=. --filename=composer.phar >/dev/null || { rm -rf "$tmp"; die "installer 执行失败"; }
  rm -rf "$tmp"
  COMPOSER_CMD=("$PHP_BIN" "$(pwd)/composer.phar")
fi
ok "composer: $(composer_version "${COMPOSER_CMD[@]}")"

# ------------------------------------------------------------------ 依赖
if [[ ! -f vendor/autoload.php ]]; then
  info "安装 composer 依赖（首次会慢一些）"
  "${COMPOSER_CMD[@]}" install --no-interaction --prefer-dist
else
  info "vendor/ 已存在，跳过 composer install"
fi
[[ -f vendor/autoload.php ]] || die "composer install 没生成 vendor/autoload.php"

# ------------------------------------------------------------------ .env
if [[ ! -f .env ]]; then
  cp .env.example .env
  info "已生成 .env"
fi

set_env() {
  local key="$1" value="$2"
  if grep -qE "^${key}=" .env; then
    awk -v k="$key" -v v="$value" 'BEGIN{FS=OFS="="} $1==k {print k"="v; next} {print}' .env > .env.tmp && mv .env.tmp .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

set_env APP_ENV local
set_env APP_DEBUG true
set_env APP_URL "http://127.0.0.1:${PORT}"
set_env DB_CONNECTION sqlite
set_env CACHE_STORE database
set_env SESSION_DRIVER database
set_env QUEUE_CONNECTION database

grep -qE '^APP_KEY=base64:' .env || { info "生成 APP_KEY"; "$PHP_BIN" artisan key:generate --force >/dev/null; }

[[ $FRESH -eq 1 ]] && { warn "--fresh：删除本地 sqlite"; rm -f database/database.sqlite; }
touch database/database.sqlite

# ------------------------------------------------------------------ 迁移/资产
info "数据库迁移 + 演示数据（幂等）"
"$PHP_BIN" artisan migrate --force --no-interaction >/dev/null
"$PHP_BIN" artisan db:seed --force --no-interaction >/dev/null

if [[ ! -f public/css/filament/forms/forms.css ]]; then
  info "发布 Filament 前端资产"
  "$PHP_BIN" artisan filament:assets >/dev/null
fi

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
# 只调目录权限，别给文件加执行位（否则 storage 下的 .gitignore 会被 git 记成 mode 变更）
find storage bootstrap/cache -type d -exec chmod 775 {} + 2>/dev/null || true
find storage bootstrap/cache -type f -exec chmod 664 {} + 2>/dev/null || true

ok "环境就绪"
cat <<EOF

平台后台    http://127.0.0.1:${PORT}/admin               admin@panel.test / Admin123456
服务商后台  http://127.0.0.1:${PORT}/provider/4f13b9d93a8afb6e98283702   provider-a@panel.test / Provider123456
节点注册    POST http://127.0.0.1:${PORT}/new
EOF

if [[ $SERVE -eq 1 ]]; then
  info "启动开发服务器（Ctrl+C 停止）"
  exec "$PHP_BIN" artisan serve --host=127.0.0.1 --port="$PORT"
else
  info "已跳过启动（--no-serve）。手动：$PHP_BIN artisan serve --port=${PORT}"
fi
