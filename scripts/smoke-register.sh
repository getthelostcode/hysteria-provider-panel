#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# 注册链路自检：验证 <BASE>/new 能收单、幂等、错误码正确
#
#   bash scripts/smoke-register.sh                                  # 默认 https://hysteria.ipx.sarl
#   bash scripts/smoke-register.sh http://127.0.0.1:8099 4f13b9d93a8afb6e98283702
#
# 会往目标库插入一台测试节点（IP 用文档网段 203.0.113.250），跑完在面板
# /admin/nodes 里删掉即可（列表行上有「删除」）。
# ---------------------------------------------------------------------------
set -euo pipefail

BASE="${1:-https://hysteria.ipx.sarl}"
BASE="${BASE%/}"
CODE="${2:-4f13b9d93a8afb6e98283702}"
IP="${TEST_IP:-203.0.113.$((RANDOM % 200 + 30))}"

pass=0
fail=0

say() { printf '%-46s %s\n' "$1" "$2"; }
expect() { # name, expected（可用 | 分隔多个，如 201|200）, actual
  local a
  IFS='|' read -ra wants <<<"$2"
  for a in "${wants[@]}"; do
    if [[ "$a" == "$3" ]]; then say "通过：$1" "→ $3"; pass=$((pass+1)); return 0; fi
  done
  say "失败：$1" "→ 期望 $2，实际 $3"; fail=$((fail+1))
}

post() { # url, body, [bearer token] → 输出 "code<TAB>body"
  local out code auth=()
  out="$(mktemp)"
  [[ -n "${3:-}" ]] && auth=(-H "Authorization: Bearer $3")
  code="$(curl -sS -o "$out" -w '%{http_code}' -m 20 -X POST "$1" \
    -H 'Content-Type: application/json' -H 'Accept: application/json' "${auth[@]}" \
    --data-binary "$2" 2>/dev/null || true)"
  [[ -n "$code" ]] || code="000"
  printf '%s\t%s\n' "$code" "$(head -c 400 "$out")"
  rm -f "$out"
}

echo "目标: ${BASE}/new    识别码: ${CODE}    测试 IP: ${IP}"
echo

IFS=$'\t' read -r code1 body1 < <(post "${BASE}/new" "{\"ip\":\"${IP}\",\"obfs_password\":\"${CODE}\",\"traffic_port\":17777,\"traffic_secret\":\"00112233445566778899aabbccddeeff0011223344556677\"}")
expect "首次注册（真实 register-hysteria 字段，无 hostname）" "201|200" "$code1"
echo "      响应: $body1"

IFS=$'\t' read -r code2 body2 < <(post "${BASE}/new" "{\"ip\":\"${IP}\",\"obfs_password\":\"${CODE}\",\"traffic_port\":17778,\"traffic_secret\":\"00112233445566778899aabbccddeeff0011223344556677\"}")
expect "同 IP 重复注册（幂等，200）" "200" "$code2"
echo "      响应: $body2"

IFS=$'\t' read -r code3 body3 < <(post "${BASE}/new" "{\"ip\":\"203.0.113.251\",\"obfs_password\":\"nope-nope\",\"traffic_port\":17779,\"traffic_secret\":\"00112233445566778899aabbccddeeff\"}")
expect "未知识别码（422 unknown_code）" "422" "$code3"
echo "      响应: $body3"

IFS=$'\t' read -r code4 body4 < <(post "${BASE}/new" '{"ip":"not-an-ip"}')
expect "字段校验失败（422 JSON）" "422" "$code4"
echo "      响应: $body4"

# 每次注册都会重新签发 token，心跳必须用**最后一次**注册拿到的那个
last_body="$body2"
[[ "$code2" == "200" || "$code2" == "201" ]] || last_body="$body1"
token="$(printf '%s' "$last_body" | sed -n 's/.*"node_token":"\([^"]*\)".*/\1/p')"
node_id="$(printf '%s' "$last_body" | sed -n 's/.*"node_id":\([0-9]*\).*/\1/p')"
if [[ -n "$token" && -n "$node_id" ]]; then
  IFS=$'\t' read -r code5 body5 < <(post "${BASE}/api/v1/nodes/${node_id}/heartbeat" '{"version":"v2.13.0","online_users":1,"tx":1048576,"rx":2097152}' "$token")
  expect "心跳（Bearer token）" "200" "$code5"
  echo "      响应: $body5"
else
  say "心跳：跳过（首次注册没拿到 node_id / node_token）" ""
fi

echo
echo "通过 ${pass} 项，失败 ${fail} 项"
[[ $fail -eq 0 ]] || exit 1
echo "提示：测试节点 IP=${IP} 已入账，可在面板 /admin/nodes 删除。"
