#!/usr/bin/env bash
# Hysteria /auth 冒烟测试
#
#   bash scripts/smoke-auth.sh                                  # 只验证接口可达
#   bash scripts/smoke-auth.sh https://hysteria.ipx.sarl <uuid>  # 再用某用户试一次 uuid 直连
#   bash scripts/smoke-auth.sh https://hysteria.ipx.sarl user@example.com:密码
#
# 期望：
#   1) 空 auth        → {"ok":false,"msg":"missing auth"}        （路由活着）
#   2) uuid 直连      → {"ok":true,"id":"<uuid>"}
#   3) 邮箱+密码      → {"ok":true,"id":"<uuid>"}
set -euo pipefail

BASE="${1:-https://hysteria.ipx.sarl}"
CREDS="${2:-}"

post() {
  curl -sS -m 10 -X POST "${BASE}/auth" \
    -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d "$1"
  echo
}

echo "== 1) 空 auth（确认 /auth 可达）"
post '{}'

if [[ -z "$CREDS" ]]; then
  echo "== 2) 跳过用户校验（没传账号）；用法：$0 ${BASE} <uuid|邮箱:密码>"
  exit 0
fi

if [[ "$CREDS" == *:* ]]; then
  AUTH_B64="$(printf '%s' "$CREDS" | base64 -w0)"
  echo "== 2) 邮箱/账号 + 密码：${CREDS%%:*}"
else
  AUTH_B64="$(printf '%s' "$CREDS" | base64 -w0)"
  echo "== 2) uuid 直连：${CREDS}"
fi

post "{\"addr\":\"127.0.0.1:0\",\"auth\":\"${AUTH_B64}\",\"tx\":0,\"rx\":0}"
