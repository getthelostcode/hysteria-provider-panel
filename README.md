# Hysteria 服务商后台（Laravel 12 + Filament 3）

节点注册接收端 + 平台/服务商两级后台。配套脚本
[`register-hysteria`](https://github.com/getthelostcode/register-hysteria) 装完 Hysteria2 后会
`POST /new` 上报节点，这里收单、入账、给服务商一个能看节点与流量的面板。

```
register-hysteria/install.sh ──POST /new──▶ 本服务 ──┬─▶ /admin            平台后台（服务商 CRUD / 全局节点 / 平台账号）
        （IP + 识别码 + 端口 + secret）               └─▶ /provider/{code}  服务商后台（只看自己识别码下的节点）
```

## 1. 能力

| 能力 | 位置 |
|---|---|
| 节点注册（按运营商识别码归到服务商，同 IP 幂等） | `POST /new` |
| 节点心跳（存活 / 版本 / 流量增量） | `POST /api/v1/nodes/{id}/heartbeat` |
| 平台后台：服务商、节点、平台账号、看板（在线数 / 今日 / 近 7 天 / 14 天趋势） | `/admin` |
| 服务商后台：我的节点、启停、重置 traffic secret、客户端配置片段、看板 | `/provider/{code}` |
| 多租户隔离：Filament 原生 tenancy（`Provider.code` 作 slug），跨租户 URL 一律 404 | 服务商面板 |

首版只做**节点接入 + 节点/流量看板**；终端用户、套餐、计费不在范围内。

## 2. 快速开始

```bash
# 本机试用：一条命令装依赖 → 写 .env/APP_KEY → 建库播种 → 发布资产 → 起服务
bash dev.sh                 # http://127.0.0.1:8099
bash dev.sh --port 8080     # 换端口
bash dev.sh --no-serve      # 只准备环境
bash dev.sh --fresh         # 删掉本地 sqlite 重来
```

`dev.sh` 不假设 composer 在 `PATH` 里：会依次试 `command -v composer`、`/usr/local/bin/composer`、
`/usr/bin/composer`、`/usr/local/bin/composer.phar`，都没有就下载 `./composer.phar`（sha384 校验）并用 PHP 调用。

手动等价步骤：

```bash
composer install
cp .env.example .env && php artisan key:generate

# SQLite（最省事）
touch database/database.sqlite && php artisan migrate --seed

# 或 MySQL 8：
# mysql -uroot -e "CREATE DATABASE hysteria_provider DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
# 改 .env 的 DB_CONNECTION=mysql + DB_* 后 php artisan migrate --seed

php artisan filament:assets        # 发布面板前端资产（缺这步页面样式会 404）
php artisan serve --port=8099
```

| 面板 | 地址 | 演示账号 |
|---|---|---|
| 平台后台 | http://127.0.0.1:8099/admin | `admin@panel.test` / `Admin123456` |
| 服务商后台 | http://127.0.0.1:8099/provider/4f13b9d93a8afb6e98283702 | `provider-a@panel.test` / `Provider123456` |

> 演示账号来自 `PlatformSeeder`，生产请立刻改密码（`/admin/platform-accounts`）。

## 3. 接口

### 3.1 节点注册 `POST /new`

```json
{
  "ip": "103.47.186.35",
  "obfs_password": "4f13b9d93a8afb6e98283702",
  "traffic_port": 10948,
  "traffic_secret": "<48 位 hex>",
  "hostname": "japan2.3ai.best",
  "version": "v2.13.0"
}
```

- `obfs_password` 就是**运营商识别码**：用它匹配 `providers.code`，节点归到该服务商
- 同一服务商下按 `ip` 幂等：重复注册刷新端口/secret，并**重新签发 token**（旧 token 立即失效）
- 响应（新建 201 / 更新 200）：

```json
{"ok":true,"node_id":4,"provider":"4f13b9d93a8afb6e98283702","status":"active",
 "node_token":"nd_xxx","heartbeat_url":"http://<host>/api/v1/nodes/4/heartbeat"}
```

| 错误 | 状态码 | 说明 |
|---|---|---|
| `unknown_code` | 422 | 识别码未登记 |
| `provider_suspended` | 403 | 服务商被停用 |
| `unauthorized` | 401 | 配置了 `REGISTER_SHARED_SECRET` 但没带 `X-Register-Secret` |
| 校验失败 | 422 | 字段缺失/格式错 |

`traffic_secret` 用 Laravel `encrypted` cast 落库，响应不回显；`node_token` 只存 sha256，明文只在注册响应出现一次。

> 这两个接口**始终返回 JSON**（内部加了 `force.json` 中间件，强制 `Accept: application/json`）：
> 即使调用方没带 Accept 头，校验失败也是 `422 + {"errors":{...}}`，不会退化成 `302 + HTML`。

### 3.2 心跳 `POST /api/v1/nodes/{id}/heartbeat`

```
Authorization: Bearer nd_xxx
{"version":"v2.13.1","online_users":7,"tx":1048576,"rx":2097152}
```

`tx`/`rx` 是**增量字节**，累加到当天 `node_traffic_daily`；存活写库按 `HEARTBEAT_WRITE_GATE`（默认 15s）节流，版本变化立即写。

### 3.3 健康检查

`GET /up` → 200（Laravel 内置）

### 3.4 采集器接口 `/api/collector/v1/*`（hysteria-traffic-collector 用）

采集器不直连数据库，全部走这组接口；鉴权头 `X-Collector-Token: <COLLECTOR_TOKEN>`。
`COLLECTOR_TOKEN` 为空时整组返回 **503**，填了但头不对返回 **401**。

| 方法 | 路径 | 用途 |
|---|---|---|
| GET | `/health` | 探活 + 待踢数 |
| GET | `/nodes` | 启用中节点（节点 active + 服务商 active）：`id name hostname api_base secret traffic_port provider` |
| POST | `/ingest` | `{node_id, polled_at, users:[{user_id,tx,rx}], online:[{user_id,connections}]}` |
| GET | `/kick-queue?limit=` | 待踢 uuid（只返回 pending） |
| POST | `/kick-result` | `{id, node_id, http_status, note}` → `kicked`(2xx) / `failed` |

```bash
TOKEN=$(grep '^COLLECTOR_TOKEN=' .env | cut -d= -f2)
curl -s -H "X-Collector-Token: $TOKEN" http://127.0.0.1:8099/api/collector/v1/nodes
```

`ingest` 语义：
- 按「节点 + 用户 + UTC 日期」累加 `node_user_traffic`（tx/rx 都是**增量**）；
- `online` 非 null 时整体替换该节点 `node_user_online`（null/缺省表示本轮保留旧快照）；
- 同时更新 `nodes.last_seen_at` 与 `nodes.last_ingest_at`；
- **幂等**：`polled_at` 不新于 `last_ingest_at` 的批次直接丢弃，返回 `{"ok":true,"duplicate":true}`。

面板后台新增三个页面（「流量与在线」分组）：**用户流量**（按节点/用户/日期查）、
**在线用户**（最近一轮快照 + 连接数）、**待踢队列**（新建 uuid 即入队，采集器 30s 内发现在线就踢）。

### 3.5 终端用户注册 `/join/{code}` 与 Hysteria `/auth`

每个运营商一个公开注册页，**用独立的 Filament 面板实现**（`JoinPanelProvider`，id=`join`，
面板路径 `join/{code}`，无登录、无后台导航，Emerald 主题与后台一致；`code` 就是租户标识，也是节点 obfs 密码）：

```
https://hysteria.ipx.sarl/join/{operator_code}/register   # 注册表单（Filament Form）
https://hysteria.ipx.sarl/join/{operator_code}/welcome    # 开通结果（Filament Table 列服务器）
https://hysteria.ipx.sarl/join/{operator_code}            # 302 → /register（旧链接兼容）
```

- 用户填 **邮箱 + 密码**（≥8 位）即开通，账号归属该运营商；邮箱全局唯一。
- 注册成功页（welcome，Filament Page + Table）内容：
  - **我的账号**：状态、到期时间、流量配额、已用流量（该 uuid 累计）、用户 ID、当前在线连接数；
  - **可用服务器**（Filament Table，不分页）：该运营商所有启用节点，显示「在线 / 离线 / 未上报」徽章、
    在线人数、连接数、最后心跳（相对 + 绝对时间）、节点版本；停用节点与其它运营商的节点不会出现；
  - **客户端配置**：每行一个「客户端配置」动作，弹窗给出可直接复制的 YAML
    （`server` 用节点 `hostname`，混淆密码 = 运营商标识）。
- 服务商后台 → **用户**（`/provider/{code}/users`）：看本运营商用户、停用/启用、改密、手工建号，
  右上角「注册链接」直接弹出该运营商的 `/join/{code}`。
- 平台后台 → **终端用户**（`/admin/end-users`）：跨运营商总览、按运营商筛选、停用/启用。
- 运营商被停用（`providers.status=suspended`）时注册页与这些用户的鉴权都返回失败。

节点鉴权端点：`POST /auth`（Hysteria2 HTTP auth 契约）

```
{"addr":"1.2.3.4:5678","auth":"<base64(邮箱:密码)>","tx":0,"rx":0}
   → {"ok":true,"id":"<uuid>"}        成功，id 就是流量/踢人用的用户 ID
   → {"ok":false,"msg":"账号已停用…"}  失败（密码错、停用、到期、运营商停用）
```

`auth` 也接受非 base64 的 `邮箱:密码`；登录名可以是邮箱，也可以是 uuid。

节点侧把鉴权指到面板（register-hysteria 支持 `AUTH_URL`）：

```bash
AUTH_URL=https://hysteria.ipx.sarl/auth ./install.sh --code <运营商识别码>
```

面板 `.env` 里两个可选变量决定欢迎页显示的服务器地址：

```
HYSTERIA_PUBLIC_HOST=japan2.3ai.best   # 默认取 APP_URL 的 host
HYSTERIA_PUBLIC_PORT=443
```

## 4. 数据表

| 表 | 关键字段 | 说明 |
|---|---|---|
| `providers` | `code`(唯一) `name` `status` | 服务商；`code` = 运营商识别码 = 租户 slug |
| `provider_users` | `provider_id` `email` `password` | 服务商后台账号（`provider` guard） |
| `admins` | `email` `password` | 平台后台账号（`admin` guard） |
| `nodes` | `provider_id` `ip` `obfs_password` `traffic_port` `traffic_secret`(加密) `api_token_hash` `version` `status` `last_seen_at` `last_ingest_at` `meta` | 唯一键 `(provider_id, ip)` |
| `node_traffic_daily` | `node_id` `date` `tx_bytes` `rx_bytes` `online_users` | 唯一键 `(node_id, date)`，`date` 存 `Y-m-d` 字符串 |
| `node_user_traffic` | `node_id` `user_id` `day` `tx_bytes` `rx_bytes` | 采集器回传的每用户按天累计，唯一键 `(node_id,user_id,day)` |
| `node_user_online` | `node_id` `user_id` `connections` `seen_at` | 最近一轮在线快照，唯一键 `(node_id,user_id)` |
| `end_users` | `provider_id` `uuid`(唯一,=Hysteria user id) `email`(唯一) `password`(hash) `status` `expires_at` `traffic_quota_bytes` `last_auth_at` `register_ip` | 终端用户，由 `/join/{code}` 自助注册或服务商后台创建 |
| `node_kick_queue` | `uuid` `reason` `status`(pending/kicked/failed) `requested_by` `node_id` `http_status` `note` `processed_at` | 待踢队列，面板加入、采集器执行并回写 |

**在线判定**：`status=active` 且 `last_seen_at` 在 `NODE_ONLINE_TTL`（默认 90s）内。

## 5. 缓存键（跨进程共享状态）

| Key | TTL | 用途 |
|---|---|---|
| `node:online:{node_id}` | `NODE_ONLINE_TTL` | 在线副本，给面板以外的消费方（agent / 告警）快速判断 |
| `node:hb_gate:{node_id}` | `HEARTBEAT_WRITE_GATE` | 心跳写库节流闸门（`Cache::add` 抢锁） |

默认 `CACHE_STORE=database`；换成 `redis` 即落到 Redis（键名不变）。

## 6. 时序

```
install.sh          /new                 DB                      面板
   │  POST 节点信息   │                   │                        │
   ├─────────────────▶ 按 code 找 provider │                        │
   │                  ├──────────────────▶│ upsert nodes           │
   │                  │  签发 node_token   │                        │
   │◀─────────────────┤                   │        /provider/{code}/nodes
   │                  │                   │◀───────────────────────┤ 读节点 + 在线判定
   │                  │                   │                        │
agent 心跳 ──Bearer──▶ /heartbeat ──gate──▶ last_seen_at / 流量累加 ─▶ 面板看板
```

## 7. 节点侧对接

```bash
# 在 register-hysteria 仓库里
./install.sh --code <运营商识别码>                              # 上报到默认 https://hysteria.ipx.sarl/new
REG_API=http://127.0.0.1:8099/new ./install.sh --code <code>    # 指向本服务
```

服务端换域名只改 `REG_API`，脚本侧不用动。注册返回的 `node_token` 需要写进节点侧 agent 才能打心跳
（当前 `register-hysteria` 未保存 token，属已知缺口）。

## 8. 测试

```bash
php artisan test     # 23 用例 / 77 断言（SQLite 内存库，见 phpunit.xml）
```

线上/联调自检（真实 HTTP，会插入一台测试节点，IP 默认取 `203.0.113.<随机>`）：

```bash
bash scripts/smoke-register.sh                                  # 默认 https://hysteria.ipx.sarl
bash scripts/smoke-register.sh http://127.0.0.1:8099 4f13b9d93a8afb6e98283702
```

自检 5 项：首次注册（只发 register-hysteria 那四个字段）、同 IP 幂等、未知识别码 422、
字段校验 422 JSON、心跳 200。跑完可在 `/admin/nodes` 点「删除」清掉测试节点。

覆盖：注册入账与幂等、未知识别码/停用服务商、共享密钥、心跳鉴权与流量累加、禁用节点拒绝、
租户隔离（列表不可见 + 跨租户 URL 404）、两个面板登录页与页面渲染、跨面板账号互斥。

## 9. 部署

```bash
DB_CONNECTION=sqlite bash install.sh                          # 或 DB_CONNECTION=mysql DB_PASSWORD=xxx bash install.sh
bash install.sh --no-services                                 # 只装应用，不写 systemd
APP_DIR=/opt/hpp APP_PORT=8099 bash install.sh                # 自定义目录/端口
```

`install.sh` 会：检查 PHP(≥8.2) 与扩展 → 装 composer（缺 `php` 命令时先补 `/usr/local/bin/php` 别名；composer 存在但跑不起来时只用
`php composer.phar` 显式调用，绝不裸调）→ 复制代码到 `APP_DIR` → 写 `.env`/`APP_KEY` → 建库 → `migrate --seed` →
发布 Filament 资产 → 写 systemd 单元（`artisan serve` 绑 `127.0.0.1:APP_PORT`）→ 验证 `/up`、`/admin/login`、`/provider/login`。可重复执行（种子幂等）。

生产建议把 `artisan serve` 换成 php-fpm + nginx（`root public/`，`try_files $uri /index.php?$query_string`），
反代 / Cloudflare Tunnel 后面要设 `TRUSTED_PROXIES`（默认 `127.0.0.1,::1`），否则节点 IP 会记成代理地址。

### 已部署实例升级（含采集器接口）

```bash
cd /var/www/hysteria-provider-panel
bash upgrade-panel.sh --app . --url https://hysteria.ipx.sarl --restart
```

它按顺序做：`git pull` → `composer install --no-dev` → 确保 `.env` 有 `COLLECTOR_TOKEN`（没有就生成）
→ `migrate --force` → **清 config/route/view/event 缓存** → `config:cache` → 打印 `api/collector/v1` 路由
→ 重载 php-fpm → 用 token 打 `/api/collector/v1/health` 自检（200/401/503/404 分别给结论）。

`--dry-run` 只打印将执行的命令。**升级后必须清 route 缓存**，否则新路由一直 404。

## 10. 排错

| 现象 | 原因 / 处理 |
|---|---|
| `require(.../vendor/autoload.php): Failed to open stream` | 依赖没装（`vendor/` 按惯例不入库）。跑 `bash dev.sh`，或手动 `php /usr/local/bin/composer.phar install`（composer 可能不在 PATH） |
| `POST /new` 带真实 payload 返回 **500 Server Error** | 部署的是旧代码（`hostname` 未做空值保护的历史 bug）。`git pull && bash install.sh` 更新后重试 |
| `/api/collector/v1/*` 全部 **404** `The route ... could not be found` | 两种原因：① 线上还是旧代码（没 pull）；② 代码更新了但 route 缓存没清。跑 `bash upgrade-panel.sh --url <面板地址>`，它会 pull + `route:clear` + 自检。若仍 404，检查 nginx `root` 是否指向同一个 `public/`、opcache 是否需 reload php-fpm |
| `/api/collector/v1/health` 返回 **503** `collector_api_disabled` | `.env` 没有 `COLLECTOR_TOKEN`；写入后 `php artisan config:cache` |
| `/api/collector/v1/health` 返回 **401** | 采集器 `panel_api.token` 与面板 `COLLECTOR_TOKEN` 不一致（改过 .env 记得重建 config 缓存） |
| `composer --version` 无输出、`timeout: failed to run command 'composer'` | 机器上只有 `php8.2` 没有 `php` 命令，而 composer 的 shebang 是 `#!/usr/bin/env php` —— 文件在也执行不了。`install.sh` 现在会自动建 `/usr/local/bin/php` 别名，并改成 `php /usr/local/bin/composer.phar` 显式调用（不再裸调 composer） |
| 面板样式全丢 | 没跑 `php artisan filament:assets` |
| `/new` 返回 422 `unknown_code` | `obfs_password` 与 `providers.code` 不一致；先在 `/admin/providers` 建服务商 |
| 节点注册了但显示离线 | 还没心跳；`last_seen_at` 超 90s 即离线，可调 `NODE_ONLINE_TTL` |
| 服务商登录后 404 | URL 必须带自己的识别码 `/provider/{code}`；跨租户会被 Filament 统一 404 |
| 平台账号进不去服务商面板 | 两个面板用不同 guard，属预期行为 |
| 心跳 401 | token 只在注册响应出现一次；重跑注册会重新签发 |
| 心跳 403 `node_disabled` | 面板里禁用过该节点 |
| 流量不涨 | 心跳 `tx`/`rx` 必须是**增量**字节，不是累计值 |

## 11. 技术栈

Laravel 12.70 · Filament 3.3.57 · PHP 8.2 · MySQL 8 / SQLite · Redis（可选，默认 database cache）

## License

MIT
