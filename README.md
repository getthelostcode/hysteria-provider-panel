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
- 节点证书指纹（`register-hysteria` 上报）存在 `nodes.cert_sha256` / `cert_pin_sha256`；
  面板生成客户端配置时有 pin 就用 `tls.insecure: false` + `pinSHA256`，没有才退回 `insecure: true`。
- 注册成功页（welcome，Filament Page + Table）内容：
  - **我的账号**：状态、到期时间、流量配额、已用流量（该 uuid 累计）、用户 ID、当前在线连接数；
  - **可用服务器**（Filament Table，不分页）：该运营商所有启用节点，显示「在线 / 离线 / 未上报」徽章、
    在线人数、连接数、最后心跳（相对 + 绝对时间）、节点版本；停用节点与其它运营商的节点不会出现；
  - **客户端配置**：每行一个「客户端配置」动作，弹窗给出可直接复制的 YAML
    （`server` 用节点 `hostname`，混淆密码 = 运营商标识）。
- 服务商后台 → **用户**（`/provider/{code}/users`）：看本运营商用户、停用/启用、改密、手工建号、
  **重置 UUID**（见下），右上角「注册链接」直接弹出该运营商的 `/join/{code}`。
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
AUTH_URL=https://auth.ipx.sarl/auth ./install.sh --code <运营商识别码>
```

面板 `.env` 里两个可选变量决定欢迎页显示的服务器地址：

```
HYSTERIA_PUBLIC_HOST=japan2.3ai.best   # 默认取 APP_URL 的 host
HYSTERIA_PUBLIC_PORT=443
```

### 3.6 终端用户中心 `/user`（登录看用量）

独立的 Filament 面板（`UserPanelProvider`，id=`user`，guard=`end_user`，Emerald 主题），
用户用注册时的**邮箱 + 密码**登录，只能看到自己的数据：

| 页面 | URL | 内容 |
|---|---|---|
| 登录 | `/user/login` | 邮箱 + 密码（支持记住我）；停用/到期/运营商停用的账号登录后被拒（403） |
| 概览 | `/user` | 累计流量、今日流量、当前连接数、可用服务器数；账号状态/到期/配额/uuid；连接方式 |
| 使用记录 | `/user/usage` | `node_user_traffic` 明细：**日期 × 服务器 × 上行/下行/合计**，可按服务器、日期区间筛选 |
| 服务器 | `/user/servers` | 该运营商每台服务器上**我自己**的上行/下行/合计、我的连接数、节点在线状态与最后心跳；每行「连接 / 二维码」直接出 hysteria2:// 链接与二维码 |

**重置 UUID**（服务商后台「用户」/ 平台后台「终端用户」的列表动作）：

- uuid 是随机生成的 32 位 hex，重置后**旧二维码/链接立即失效**（uuid 就是客户端账号+密码），
  `connect_token` 一起轮换；用户密码不变，仍可登录用户中心重新生成二维码；
- 历史 `node_user_traffic` / `node_user_online` 会迁移到新 uuid，用户中心的用量记录不会断。

**连接链接与二维码**（服务器页每行「连接 / 二维码」）：

```
hysteria2://<uuid>@<host>:443/?sni=<sni>&obfs=salamander
          &obfs-password=<运营商识别码>&pinSHA256=<证书pin>#<host>
```

例：
`hysteria2://wrx4…nwcul@104.223.108.120:443/?sni=bing.com&obfs=salamander&obfs-password=4f13b9…3702&pinSHA256=7AFBCAF6EE063AD5EC4FE11FE87B790FD07492D568484231029FFF3C3F2063EC#104.223.108.120`

- **URL 里只有账号（uuid），不带密码**；`/auth` 认四种凭据：
  `uuid`（只有 uuid，二维码/链接用）、`uuid:uuid`、`邮箱:密码`（门户登录那套）、
  `uuid:connect_token`（更早的链接，仍兼容）；
- `sni`：**固定 `bing.com`**（不随节点域名变化，TLS 只靠 pinSHA256 校验）；
- `#` 片段是节点地址（`#104.223.108.120`）；
- 证书指纹取 **`register-hysteria` 上报的证书 SHA256**，写成 **hex 大写、不带冒号**
  （`7A:FB:CA:…` → `7AFBCA…`）；只有 base64 的 `cert_pin_sha256` 时会自动转成 hex；
  两者都没有才退回 `insecure=1`（重跑 `install.sh` 补报指纹）；
- IPv6 主机自动加方括号；
- 二维码由 `endroid/qr-code` 在服务端生成 SVG（不引 CDN、不落地文件），弹窗里可直接扫码，
  同时给出链接和复制按钮；token 泄露可在服务商后台重置用户密码/停用账号后处理。

实现要点：
- `EndUser` 继承 `Authenticatable` 并实现 `FilamentUser`（`canAccessPanel` 只允许 active 且运营商正常的账号）
  与 `HasName`（显示名 = 昵称或邮箱）；`config/auth.php` 新增 `end_user` guard + `end_users` provider。
- 服务器页用子查询（`selectSub`）按当前用户聚合 `node_user_traffic` / `node_user_online`，
  不额外建表，数据与采集器回传实时一致。
- 默认语言改为 `zh_CN`（`APP_LOCALE=zh_CN`），Filament 自带中文翻译，登录页/表格/分页都是中文。

### 3.7 标准版 Hysteria 配置模板（后台可编辑）

平台后台 **系统 → Hysteria 配置模板**（`/admin/config-template`）：

- 一个多行编辑器直接改 `config.yaml` 标准版，点「保存并递增版本」写入 `config_templates`（`version+1`、记录保存人）；
- 「恢复默认内容」只填充表单，不保存；
- 顶部显示当前版本、最近保存时间、保存人、内容 SHA256、拉取地址；
- 节点安装脚本**只从这里拉取**（`register-hysteria` 仓库已不再放 `config.yaml.example`），用本节点的识别码 / trafficStats 端口与 secret 覆盖后再写入 `/etc/hysteria/config.yaml`；拉取失败安装会直接退出
  （`obfs.salamander.password`、`trafficStats.listen/secret`、`tls.cert/key`、`bandwidth` 会被覆盖，其余字段原样生效）。

**服务商后台 → 配置模板**（`/provider/{code}/config-template`）：

- 顶部显示当前生效来源（平台标准 / 服务商自定义）、版本、SHA256、本运营商的拉取地址；
- 多行编辑器写自定义 `config.yaml`，保存后版本自增；**清空内容保存 = 回到平台标准**；
- 页面**中间（左对齐）**给出「一键安装命令」（含本运营商识别码），复制到节点直接执行：
  ```bash
  curl -fsSL https://hysteria.ipx.sarl/api/install.sh | sudo bash -s -- --code <运营商识别码>
  ```
  脚本由面板自己下发（`GET /api/install.sh`，见下），不依赖 GitHub 可访问性。
  下方还折叠了「先下载再执行」的等价命令，以及 `--dry-run` / `--register-only` / `--template-file` 的用法提示。

生效顺序：**服务商自定义模板 > 平台标准模板**。

#### 一键安装脚本 `/api/install.sh`

- 脚本文件放在 `resources/hysteria/install.sh`（就是 `register-hysteria` 的 `install.sh`），
  `GET /api/install.sh` 以 `text/plain` 返回，带 `X-Install-Script-Sha256` 头；
- 页面底部会显示脚本 sha256、更新时间、体积，方便核对线上跑的是哪一版；
- **`register-hysteria/install.sh` 更新后要同步过来**：
  ```bash
  cp /path/to/register-hysteria/install.sh resources/hysteria/install.sh
  ```
- 想走 CDN/镜像：设 `INSTALL_SCRIPT_URL=https://cdn.example.com/install.sh`（留空 = 用面板自己的 `/api/install.sh`）。

节点侧接口（`install.sh` 会带自己的识别码）：

```
GET /api/config-template?code=<运营商识别码>
{"name":"standard","version":3,"source":"provider|standard","sha256":"…","updated_at":"…","content":"listen: ':443'\n…"}
```

未知 code 或不带 code 都回退平台标准（不报错）。

节点上报里带 `config_version`，后台节点详情的「运行状态」会显示「配置模板版本 vN」，
方便核对节点实盘配置来自哪一版标准模板。

### 3.8 鉴权接口后台自测 `/admin/auth-endpoint`

平台后台 **系统 → 鉴权接口**（`/admin/auth-endpoint`）：

- 显示节点侧应配的鉴权地址（`config/hysteria.php` 的 `auth_url`，默认 `https://auth.ipx.sarl/auth`，
  可用 `HYSTERIA_AUTH_URL` 覆盖）、用户数/启用数、`auth.http.url` 配置、请求体样例、curl 样例；
  地址不是面板自己时，自测会**真的发 HTTP 请求到该地址**（测的就是独立鉴权服务）；
- **在线自测**：留空 = 只验证接口可达（返回 `missing auth` 即路由正常）；
  填 uuid = uuid 直连模式；填账号+密码 = 邮箱密码 / `uuid:token`；
  结果直接显示 `{"ok":true,"id":"…"}` 或失败原因。

命令行等价：

```bash
bash scripts/smoke-auth.sh                                        # 只探活（默认 auth.ipx.sarl）
bash scripts/smoke-auth.sh https://auth.ipx.sarl <uuid>           # uuid 直连
bash scripts/smoke-auth.sh https://auth.ipx.sarl 'a@b.com:密码'    # 邮箱+密码
```

线上冒烟（2026-10-18 验证）：

```
POST https://hysteria.ipx.sarl/auth {} → 200 {"ok":false,"msg":"missing auth"}   # 面板兜底（旧地址）
GET  https://hysteria.ipx.sarl/auth    → 405（只接受 POST，符合 Hysteria 契约）

节点侧现在用独立鉴权服务：POST https://auth.ipx.sarl/auth
（或同机 http://127.0.0.1:8091/auth，见 https://github.com/getthelostcode/hysteria-auth）
```

### 3.9 服务商自助注册 `/provider-signup`

服务商自己开通，不用平台管理员手工建：

```
GET  /provider-signup        注册表单（运营商名称、识别码、登录邮箱、密码、联系方式）
POST /provider-signup        建 providers + provider_users，自动登录并跳到 /provider/{code}
```

- **登录账号就是注册时填的邮箱 + 密码**，属于 `provider` guard，只能进自己那一个租户
- 识别码可留空自动生成（`p` + 10 位随机），手输大写会自动转小写；格式限制小写字母/数字/`-`/`_`
- 同时校验识别码、邮箱唯一；密码至少 8 位且两次一致（`password_confirmation`）
- 注册成功即 `active`，直接可用；服务商登录页底部有「注册服务商」入口
- 关掉公开注册：`.env` 加 `PROVIDER_SIGNUP_ENABLED=false`（两个路由都 404）
- 防刷：POST 限流 10 次/分钟/IP；后台「服务商」列表能看到自助注册的运营商（`notes` 记录来源时间）

```bash
curl -s https://<面板域名>/provider-signup | grep -o '<title>.*</title>'
```

### 3.10 宣传站 `/site/`

对外站点随面板发布，不用单独域名，两个页面分别对两类人说话：

- `/site/`（`index.html`）：**服务商/个人接入**——把节点接进来，平台提供 HTTP 鉴权、服务器与用户流量、用户注册页；主 CTA 是 `provider-signup`
- `/site/free.html`：**终端用户**——10 GB 体验流量账号，填服务商识别码跳 `/join/{code}`；支持 `?code=` 预填，服务商可直接转发

静态文件：

```
public/site/index.html     服务商接入页
public/site/free.html      10 GB 体验页（用户）
public/site/assets/{styles.css,free.css,app.js,free.js,favicon.svg}
```

Laravel 侧只兜底两个入口（`/site`、`/site/` → `/site/index.html`）；资源由 nginx 直接出。nginx 加：

```nginx
location = /site { return 301 /site/; }

location /site/ {
    index index.html;
    try_files $uri $uri/ /site/index.html;
}
```

SEO：`public/site/sitemap.xml` 列出三个公开页（`/site/`、`/site/free.html`、`/provider-signup`），
`public/robots.txt` 指向它并屏蔽 `/admin`、`/provider/`、`/user`、`/api/`。
新增公开页时同步改 `sitemap.xml` 的 `<loc>` 与 `<lastmod>`，再去 Google Search Console / Bing 提交一次。

更新宣传页：改 `public/site/index.html`（或从独立仓库 `hysteria-site` 同步整目录）后直接生效，无需 `artisan` 命令。访问：

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://<面板域名>/site/
```

### 3.11 流量配额与周期（如 10 GB / 7 天）

两类角色都能设：

- **服务商后台 → 配置 → 套餐默认值**（`/provider/{code}/quota`）：新注册用户默认套用的配额与周期，
  例如 10 GB / 7 天；只影响之后新建的用户
- **服务商后台 → 用户 → 编辑**：单个用户的「流量配额（GB）」与「重置周期（天）」；留空 = 不限 / 不重置
- **服务商后台 → 用户（列表）**：每行显示「本期用量」（有配额时 `6.4 GB / 10 GB`，不限时只显示已用）、
  「下次重置」与「累计用量」（带 ↑上行 / ↓下行），数据来自 `node_user_traffic`
- **服务商后台 → 我的节点**：每台节点显示 IP（副标题为主机名）、「今日流量」与「累计流量」（同样分上下行）
- 用户列表每行显示「本期用量」（如 `6.5 GB / 10 GB`）与「下次重置」，并提供「清零本期用量」

行为：

| 场景 | 结果 |
|---|---|
| 新用户注册（`/join/{code}`） | 自动写入服务商的默认配额、周期与首次重置时间 |
| 采集器回传流量 | 累加到 `end_users.quota_used_bytes`；若已过重置时间，先清零再累加 |
| 用户连接（`/auth`） | 先滚动周期；本期用完返回 `{"ok":false,"msg":"流量已用完，请联系服务商"}` |
| 周期到达 | 惰性重置：下次鉴权或下一次流量回传时清零，并把下次重置推到未来 |
| 手动清零 | 用户列表「清零本期用量」，从现在重新算一个周期 |

实现说明：

- 用量双写：`node_user_traffic` 保留按天/按节点历史（用户中心图表用），`quota_used_bytes` 只表示**本期**
- 周期为空 = 一次性额度，用完不恢复；周期为 7 = 每 7 天清零
- 独立鉴权服务 `hysteria-auth` 同样读这四个字段并在过期时清零，所以它的数据库账号需要
  `end_users` 的 `UPDATE` 权限（不再是纯只读）
- 超额不会主动断开已建立的连接，只拒绝下一次连接；要立刻断人用「加入待踢」

## 4. 数据表

| 表 | 关键字段 | 说明 |
|---|---|---|
| `providers` | `code`(唯一) `name` `status` | 服务商；`code` = 运营商识别码 = 租户 slug |
| `provider_users` | `provider_id` `email` `password` | 服务商后台账号（`provider` guard） |
| `admins` | `email` `password` | 平台后台账号（`admin` guard） |
| `nodes` | `provider_id` `ip` `obfs_password` `traffic_port` `traffic_secret`(加密) `cert_sha256` `cert_pin_sha256` `api_token_hash` `version` `config_version` `status` `last_seen_at` `last_ingest_at` `meta` | 唯一键 `(provider_id, ip)` |
| `node_traffic_daily` | `node_id` `date` `tx_bytes` `rx_bytes` `online_users` | 唯一键 `(node_id, date)`，`date` 存 `Y-m-d` 字符串 |
| `node_user_traffic` | `node_id` `user_id` `day` `tx_bytes` `rx_bytes` | 采集器回传的每用户按天累计，唯一键 `(node_id,user_id,day)` |
| `node_user_online` | `node_id` `user_id` `connections` `seen_at` | 最近一轮在线快照，唯一键 `(node_id,user_id)` |
| `end_users` | `provider_id` `uuid`(唯一,=Hysteria user id) `email`(唯一) `password`(hash) `status` `expires_at` `traffic_quota_bytes` `last_auth_at` `register_ip` | 终端用户，由 `/join/{code}` 自助注册或服务商后台创建 |
| `config_templates` | `name`(唯一) `content` `version` `updated_by` | 平台标准版 Hysteria config.yaml，后台可编辑，版本自增 |
| `provider_config_templates` | `provider_id`(唯一) `content` `version` `updated_by` | 服务商自定义模板，存在时覆盖平台标准 |
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
php artisan panel:check        # 逐项体检，全 ✅ 才算部署完整
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
| 用户面板「连接 / 二维码」打开报错 / 二维码空白 | `git pull` 后没装新依赖（`endroid/qr-code`）：`composer install --no-dev`；现在二维码失败会降级成提示+链接，不再 500。体检：`php artisan panel:check` |
| 部署后不确定缺什么 | `php artisan panel:check`：逐项检查 PHP/依赖/二维码库/`/auth` 等路由/`COLLECTOR_TOKEN`/数据表/标准模板/`install.sh`/storage 可写 |
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
