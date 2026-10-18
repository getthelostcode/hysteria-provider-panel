#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# hysteria-provider-panel 升级脚本：拉代码 → 依赖 → 迁移 → COLLECTOR_TOKEN
#                               → 清缓存（含 route 缓存）→ 自检
#
#   bash upgrade-panel.sh                          # 在项目目录里直接跑
#   bash upgrade-panel.sh --app /var/www/hysteria-provider-panel
#   bash upgrade-panel.sh --restart                # 顺带 reload php-fpm
#   bash upgrade-panel.sh --dry-run                # 只打印将执行的命令
#
# 用法场景：采集器报
#   HTTP 404 {"message":"The route api/collector/v1/nodes could not be found."}
# 说明线上面板还是旧代码，或改完代码没清 route 缓存。
# ---------------------------------------------------------------------------
set -euo pipefail

APP_DIR="${APP_DIR:-$(pwd)}"
PHP_BIN="${PHP_BIN:-}"
DO_PULL=1
DO_RESTART=0
DRY=0
HEALTH_URL=""
BASE_URL=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --app) APP_DIR="${2:?--app 需要目录}"; shift 2 ;;
    --php) PHP_BIN="${2:?--php 需要路径}"; shift 2 ;;
    --url) BASE_URL="${2:?--url 需要面板地址}"; shift 2 ;;
    --no-pull) DO_PULL=0; shift ;;
    --restart) DO_RESTART=1; shift ;;
    --dry-run) DRY=1; shift ;;
    -h|--help) awk 'NR>2 { if ($0 !~ /^#/) exit; sub(/^# ?/, ""); print }' "$0"; exit 0 ;;
    *) echo "[FAIL] 未知参数: $1" >&2; exit 1 ;;
  esac
done

info() { printf '[INFO] %s\n' "$*"; }
ok()   { printf '[ OK ] %s\n' "$*"; }
warn() { printf '[WARN] %s\n' "$*"; }
die()  { printf '[FAIL] %s\n' "$*" >&2; exit 1; }
run()  {
  printf '       $ %s\n' "$*"
  [[ $DRY -eq 1 ]] || "$@"
}

cd "$APP_DIR" || die "目录不存在: $APP_DIR"
[[ -f artisan ]] || die "$APP_DIR 不是 Laravel 项目（缺 artisan）"

if [[ -z "$PHP_BIN" ]]; then
  PHP_BIN="$(command -v php || command -v php8.2 || command -v php8.3 || true)"
fi
[[ -n "$PHP_BIN" ]] || die "找不到 php"
ok "项目目录: $APP_DIR"
ok "PHP: $PHP_BIN（$("$PHP_BIN" -r 'echo PHP_VERSION;')）"

# ---------------------------------------------------------------- 1. 代码
if [[ -d .git && $DO_PULL -eq 1 ]]; then
  info "当前提交: $(git rev-parse --short HEAD) $(git log -1 --pretty=%s)"
  info "拉取最新代码"
  run git pull --ff-only
else
  warn "跳过 git pull（非 git 仓库或 --no-pull）"
fi
info "之后提交: $(git rev-parse --short HEAD 2>/dev/null || echo '—')"

# ---------------------------------------------------------------- 2. 依赖
if [[ -f composer.json ]]; then
  if [[ -f composer.phar ]]; then
    run "$PHP_BIN" composer.phar install --no-interaction --prefer-dist --no-dev --optimize-autoloader
  elif command -v composer >/dev/null 2>&1; then
    run composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
  else
    warn "没有 composer，跳过依赖安装（生产机通常已装过）"
  fi
fi

# ---------------------------------------------------------------- 3. 令牌
[[ -f .env ]] || die "缺 .env（先 cp .env.example .env && php artisan key:generate）"
TOKEN="$(grep -E '^COLLECTOR_TOKEN=' .env | head -1 | cut -d= -f2- || true)"
if [[ -z "$TOKEN" ]]; then
  TOKEN="$(openssl rand -hex 24 2>/dev/null || head -c 48 /dev/urandom | od -An -tx1 | tr -d ' \n')"
  printf '\nCOLLECTOR_TOKEN=%s\n' "$TOKEN" >> .env
  ok "已写入 COLLECTOR_TOKEN（新生成）"
else
  ok "COLLECTOR_TOKEN 已存在（长度 ${#TOKEN}）"
fi

# ---------------------------------------------------------------- 4. 迁移
info "执行数据库迁移"
run "$PHP_BIN" artisan migrate --force

# ---------------------------------------------------------------- 5. 清缓存
# 关键：route 缓存不清，新路由会一直 404
info "清 config/route/view 缓存"
run "$PHP_BIN" artisan config:clear
run "$PHP_BIN" artisan route:clear
run "$PHP_BIN" artisan view:clear
run "$PHP_BIN" artisan event:clear

if [[ $DRY -eq 0 ]]; then
  # Filament 静态资源（新增页面不需要重新构建，但补齐一下）
  if "$PHP_BIN" artisan list 2>/dev/null | grep -q 'filament:assets'; then
    "$PHP_BIN" artisan filament:assets >/dev/null 2>&1 || warn "filament:assets 失败（可忽略）"
  fi
  # 重新缓存配置（COLLECTOR_TOKEN 才会生效）
  "$PHP_BIN" artisan config:cache >/dev/null 2>&1 || warn "config:cache 失败，保持未缓存运行"
  ok "缓存已重置并重建 config 缓存"
fi

# ---------------------------------------------------------------- 6. 自检
if [[ $DRY -eq 0 ]]; then
  info "路由自检（应看到 5 条 api/collector/v1）"
  ROUTES="$("$PHP_BIN" artisan route:list --path=api/collector 2>/dev/null | grep -c 'api/collector/v1' || true)"
  if [[ "${ROUTES:-0}" -ge 5 ]]; then
    ok "api/collector/v1 路由已注册（${ROUTES} 条）"
    "$PHP_BIN" artisan route:list --path=api/collector 2>/dev/null | sed 's/^/       /'
  else
    warn "只找到 ${ROUTES:-0} 条 api/collector/v1 路由 —— 代码可能没更新到最新（git log 看看）"
  fi
fi

# ---------------------------------------------------------------- 7. 重启
if [[ $DO_RESTART -eq 1 ]]; then
  info "重载 PHP-FPM / 队列"
  if command -v systemctl >/dev/null 2>&1; then
    for svc in php8.2-fpm php8.3-fpm php8.fpm php-fpm; do
      systemctl list-unit-files 2>/dev/null | grep -q "^${svc}.service" && { run systemctl reload "$svc" || true; }
    done
  fi
fi

echo
echo "================ 升级完成 ================"
cat <<EOF
项目       : ${APP_DIR}
提交       : $(git rev-parse --short HEAD 2>/dev/null || echo '—')
COLLECTOR_TOKEN : ${TOKEN}
采集器配置 : panel_api.token 填上面这个值
EOF
echo
if [[ -n "$BASE_URL" ]]; then
  info "远程自检 ${BASE_URL}/api/collector/v1/health"
  code="$(curl -s -o /tmp/panel-health.out -w '%{http_code}' -m 10 -H "X-Collector-Token: ${TOKEN}" "${BASE_URL}/api/collector/v1/health" || true)"
  echo "       HTTP ${code}  $(head -c 200 /tmp/panel-health.out 2>/dev/null)"
  case "$code" in
    200) ok "采集器接口可用" ;;
    401) warn "路由在，但 token 不一致（面板 .env 与本脚本读到的是同一个？config:cache 已重建）" ;;
    503) warn "路由在，但 COLLECTOR_TOKEN 为空 —— 检查 .env 是否被 config:cache 之前写入" ;;
    404) warn "仍是 404：代码没更新到最新，或 web 服务指向了另一个目录/opcache 未刷新" ;;
  esac
fi
