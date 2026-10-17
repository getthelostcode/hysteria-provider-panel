#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Hysteria 服务商后台（Laravel 12 + Filament 3）一键安装脚本
#
# 用法:
#   bash install.sh                     # SQLite（最省事，默认）
#   DB_CONNECTION=mysql bash install.sh # MySQL 8（需 DB_* 环境变量）
#   bash install.sh --no-services       # 只装应用，不写 systemd 单元、不启动
#   bash install.sh --optimize          # 生产优化（config/route/view 缓存）
#
# 装完:
#   平台后台  http://<host>:<port>/admin
#   服务商后台 http://<host>:<port>/provider/<运营商识别码>
#   节点注册   POST http://<host>:<port>/new
#
# 环境变量:
#   APP_DIR=/opt/hysteria-provider-panel   APP_PORT=8099
#   DB_CONNECTION=sqlite|mysql  DB_HOST=127.0.0.1 DB_PORT=3306
#   DB_DATABASE=hysteria_provider  DB_USERNAME=root DB_PASSWORD=
#   ADMIN_EMAIL=admin@panel.test  ADMIN_PASSWORD=Admin123456
#   SYSTEMD_UNIT_DIR=/etc/systemd/system  UNIT_BASE=hysteria-provider-panel
# ---------------------------------------------------------------------------
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/hysteria-provider-panel}"
APP_PORT="${APP_PORT:-8099}"
DB_CONNECTION="${DB_CONNECTION:-sqlite}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-hysteria_provider}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@panel.test}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-Admin123456}"
SYSTEMD_UNIT_DIR="${SYSTEMD_UNIT_DIR:-/etc/systemd/system}"
UNIT_BASE="${UNIT_BASE:-hysteria-provider-panel}"
PHP_BIN="${PHP_BIN:-}"

WITH_SERVICES=1
OPTIMIZE=0

for arg in "$@"; do
  case "$arg" in
    --no-services) WITH_SERVICES=0 ;;
    --optimize) OPTIMIZE=1 ;;
    -h|--help) awk 'NR>2 { if ($0 !~ /^#/) exit; sub(/^# ?/, ""); print }' "$0"; exit 0 ;;
    *) echo "[FAIL] 未知参数: $arg" >&2; exit 1 ;;
  esac
done

info() { printf '[INFO] %s\n' "$*"; }
ok()   { printf '[ OK ] %s\n' "$*"; }
warn() { printf '[WARN] %s\n' "$*"; }
die()  { printf '[FAIL] %s\n' "$*" >&2; exit 1; }

run_t() {
  local secs="$1"; shift
  if command -v timeout >/dev/null 2>&1; then timeout "$secs" "$@"; else "$@"; fi
}

[[ ${EUID} -eq 0 ]] || die "需要 root 运行（会写 ${APP_DIR} / ${SYSTEMD_UNIT_DIR}）"

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
[[ -f "${SCRIPT_DIR}/artisan" ]] || die "请在仓库根目录执行（找不到 artisan）"

# ------------------------------------------------------------------ PHP/依赖
command -v php >/dev/null 2>&1 || die "缺少 php。Debian/Ubuntu: apt install -y php8.2-cli php8.2-mbstring php8.2-xml php8.2-intl php8.2-bcmath php8.2-zip php8.2-sqlite3 php8.2-mysql"
PHP_BIN="${PHP_BIN:-$(command -v php)}"
PHP_BIN="$(readlink -f "$PHP_BIN")"

PHP_VERSION="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
info "PHP ${PHP_VERSION} → ${PHP_BIN}"
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
  || die "需要 PHP >= 8.2，当前 ${PHP_VERSION}"

REQUIRED_EXT=(mbstring xml intl bcmath zip json openssl)
[[ "$DB_CONNECTION" == "mysql" ]] && REQUIRED_EXT+=(pdo_mysql) || REQUIRED_EXT+=(pdo_sqlite)
for ext in "${REQUIRED_EXT[@]}"; do
  "$PHP_BIN" -m | grep -qx "$ext" || die "缺少 PHP 扩展: ${ext}（Debian/Ubuntu: apt install -y php8.2-${ext}；pdo_sqlite 的包名是 php8.2-sqlite3，pdo_mysql 是 php8.2-mysql）"
done
ok "PHP 扩展检查通过：${REQUIRED_EXT[*]}"
# 说明：pdo_sqlite 的 apt 包名是 php8.2-sqlite3，pdo_mysql 是 php8.2-mysql

# ------------------------------------------------------------------ composer
if ! command -v composer >/dev/null 2>&1; then
  warn "未找到 composer，尝试从官方安装器安装到 /usr/local/bin"
  tmp="$(mktemp -d)"
  run_t 60 curl -sS https://getcomposer.org/installer -o "$tmp/installer" || die "下载 composer 安装器失败"
  EXPECTED="$(run_t 30 curl -sS https://composer.github.io/installer.sig || true)"
  ACTUAL="$("$PHP_BIN" -r "echo hash_file('sha384', '${tmp}/installer');")"
  [[ -n "$EXPECTED" && "$EXPECTED" == "$ACTUAL" ]] || die "composer 安装器签名校验失败"
  "$PHP_BIN" "$tmp/installer" --install-dir=/usr/local/bin --filename=composer >/dev/null
  rm -rf "$tmp"
  ok "composer 已安装：$(composer --version 2>/dev/null | head -1)"
fi

# ------------------------------------------------------------------ 代码落地
if [[ "$(readlink -f "$SCRIPT_DIR")" != "$(readlink -f "$APP_DIR" 2>/dev/null || echo "")" ]]; then
  info "复制代码到 ${APP_DIR}"
  mkdir -p "$APP_DIR"
  tar -C "$SCRIPT_DIR" \
    --exclude='./.git' --exclude='./vendor' --exclude='./node_modules' \
    --exclude='./.env' --exclude='./database/*.sqlite' --exclude='./storage/logs/*' \
    -cf - . | tar -C "$APP_DIR" -xf -
fi
cd "$APP_DIR"

info "安装 composer 依赖"
COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 \
  run_t 600 composer install --no-interaction --prefer-dist --optimize-autoloader </dev/null \
  | tail -3
ok "依赖就绪"

# ------------------------------------------------------------------ .env
if [[ ! -f .env ]]; then
  cp .env.example .env
  info "已生成 .env"
fi

set_env() {
  local key="$1" value="$2"
  if grep -qE "^${key}=" .env; then
    # 只替换值，保留注释与顺序（phpdotenv 只认第一次出现的键，不能 append）
    awk -v k="$key" -v v="$value" 'BEGIN{FS=OFS="="} $1==k {print k"="v; next} {print}' .env > .env.tmp
    mv .env.tmp .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

set_env APP_NAME '"Hysteria Provider Panel"'
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "http://127.0.0.1:${APP_PORT}"
set_env DB_CONNECTION "$DB_CONNECTION"
set_env SESSION_DRIVER database
set_env QUEUE_CONNECTION database
set_env CACHE_STORE database

if [[ "$DB_CONNECTION" == "mysql" ]]; then
  set_env DB_HOST "$DB_HOST"
  set_env DB_PORT "$DB_PORT"
  set_env DB_DATABASE "$DB_DATABASE"
  set_env DB_USERNAME "$DB_USERNAME"
  set_env DB_PASSWORD "$DB_PASSWORD"

  command -v mysql >/dev/null 2>&1 || die "DB_CONNECTION=mysql 但没装 mysql 客户端"
  MYSQL_ARGS=(-h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME")
  [[ -n "$DB_PASSWORD" ]] && MYSQL_ARGS+=("-p${DB_PASSWORD}")
  info "创建数据库 ${DB_DATABASE}（若不存在）"
  mysql "${MYSQL_ARGS[@]}" -e "CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;" \
    || die "建库失败：检查 DB_* 与 mysql 服务状态"
else
  touch database/database.sqlite
  chmod 666 database/database.sqlite
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
  info "生成 APP_KEY"
  "$PHP_BIN" artisan key:generate --force >/dev/null
fi

# ------------------------------------------------------------------ 迁移/资产
info "执行数据库迁移 + 种子"
"$PHP_BIN" artisan migrate --force --no-interaction
"$PHP_BIN" artisan db:seed --force --no-interaction

info "发布 Filament 前端资产"
"$PHP_BIN" artisan filament:assets >/dev/null
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [[ $OPTIMIZE -eq 1 ]]; then
  info "生产优化：config/route/view 缓存"
  "$PHP_BIN" artisan config:cache >/dev/null
  "$PHP_BIN" artisan route:cache >/dev/null
  "$PHP_BIN" artisan view:cache >/dev/null
fi

# 确保平台账号存在
"$PHP_BIN" artisan tinker --execute="App\\Models\\Admin::query()->firstOrCreate(['email'=>getenv('ADMIN_EMAIL') ?: '${ADMIN_EMAIL}'], ['name'=>'平台管理员','password'=>getenv('ADMIN_PASSWORD') ?: '${ADMIN_PASSWORD}']);" >/dev/null 2>&1 || \
  warn "创建平台账号失败，可手动执行：php artisan tinker"

# ------------------------------------------------------------------ systemd
UNIT_FILE="${SYSTEMD_UNIT_DIR}/${UNIT_BASE}.service"
if [[ $WITH_SERVICES -eq 1 && -d "$SYSTEMD_UNIT_DIR" ]] && command -v systemctl >/dev/null 2>&1; then
  info "写入 systemd 单元 ${UNIT_FILE}"
  cat > "$UNIT_FILE" <<EOF
[Unit]
Description=Hysteria Provider Panel (Laravel)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=root
WorkingDirectory=${APP_DIR}
ExecStart=${PHP_BIN} artisan serve --host=127.0.0.1 --port=${APP_PORT}
Restart=always
RestartSec=3
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
EOF
  chmod 644 "$UNIT_FILE"
  systemd-analyze verify "$UNIT_FILE" >/dev/null 2>&1 || warn "systemd-analyze verify 报错（继续）"
  systemctl daemon-reload
  systemctl enable "$UNIT_BASE" >/dev/null 2>&1 || true
  systemctl restart "$UNIT_BASE" || die "启动失败：journalctl -u ${UNIT_BASE} -n 40 --no-pager"
  sleep 2
  systemctl is-active --quiet "$UNIT_BASE" && ok "${UNIT_BASE} 已 active" || warn "${UNIT_BASE} 未 active"
else
  warn "跳过 systemd（--no-services 或本机没有 systemd）。手动启动：cd ${APP_DIR} && php artisan serve --port=${APP_PORT}"
fi

# ------------------------------------------------------------------ 验证
BASE="http://127.0.0.1:${APP_PORT}"
ok "— 验证 —"
for path in /up /admin/login /provider/login; do
  code="$(curl -s -o /dev/null -w '%{http_code}' -m 5 "${BASE}${path}" || true)"
  [[ -n "$code" ]] || code="000"
  if [[ "$code" == "200" ]]; then ok "${path} → ${code}"; else warn "${path} → ${code}（000=没起来/端口不对）"; fi
done

echo
echo "================ 安装摘要 ================"
cat <<EOF
应用目录    : ${APP_DIR}
数据库      : ${DB_CONNECTION}$([[ "$DB_CONNECTION" == "mysql" ]] && echo " (${DB_DATABASE}@${DB_HOST}:${DB_PORT})")
平台后台    : ${BASE}/admin
服务商后台  : ${BASE}/provider/<运营商识别码>
节点注册    : POST ${BASE}/new
平台账号    : admin@panel.test / Admin123456 或 ADMIN_EMAIL/ADMIN_PASSWORD 覆盖
测试服务商  : provider-a@panel.test / Provider123456（识别码 4f13b9d93a8afb6e98283702）
EOF
echo
warn "默认密码请立刻在面板里改掉（平台账号在 /admin/platform 账号）"
info "日志：journalctl -u ${UNIT_BASE} -f --no-pager"
