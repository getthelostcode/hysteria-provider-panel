<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $provider->name }} · 注册成功</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; min-height: 100vh; display: flex; align-items: flex-start; justify-content: center;
         font: 15px/1.6 -apple-system, "Segoe UI", "Noto Sans SC", sans-serif;
         background: radial-gradient(1200px 600px at 50% -10%, #d1fae5 0%, #f8fafc 55%); color: #0f172a; padding: 32px 20px; }
  .card { width: 100%; max-width: 760px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
          box-shadow: 0 12px 32px rgba(15, 23, 42, .08); padding: 28px; }
  h1 { margin: 0 0 4px; font-size: 20px; }
  h2 { font-size: 14px; margin: 26px 0 8px; color: #334155; }
  .sub { color: #64748b; font-size: 13px; margin-bottom: 20px; }
  .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; border-radius: 9px;
        padding: 10px 12px; font-size: 13px; margin-bottom: 18px; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  th { text-align: left; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;
       letter-spacing: .03em; border-bottom: 1px solid #e2e8f0; padding: 8px; white-space: nowrap; }
  td { padding: 9px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
  td.num, th.num { text-align: right; }
  .who { font-weight: 600; }
  .addr { color: #64748b; font-size: 12.5px; }
  .dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 5px; vertical-align: 1px; }
  .green .dot { background: #10b981; } .green.t { color: #047857; }
  .red .dot { background: #ef4444; } .red.t { color: #b91c1c; }
  .gray .dot { background: #94a3b8; } .gray.t { color: #64748b; }
  .kv { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px 18px;
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; }
  .kv .k { color: #64748b; font-size: 12px; }
  .kv .v { font-weight: 600; }
  code, pre { font-family: ui-monospace, Menlo, Consolas, monospace; }
  code { background: #f1f5f9; padding: 2px 6px; border-radius: 5px; font-size: 12.5px; }
  pre { background: #0f172a; color: #e2e8f0; padding: 12px 14px; border-radius: 10px; overflow-x: auto; font-size: 12.5px; margin: 8px 0 0; }
  details summary { cursor: pointer; color: #059669; font-size: 13px; }
  details[open] summary { margin-bottom: 4px; }
  .hint { color: #64748b; font-size: 12px; }
  .empty { color: #94a3b8; font-size: 13px; padding: 10px 0; }
</style>
</head>
<body>
<div class="card">
  <h1>注册成功</h1>
  <div class="sub">{{ $provider->name }} · 账号已开通，可直接在客户端使用</div>

  <div class="ok">登录客户端用「{{ $user->email }}」+ 你刚才设置的密码；密码页面不再显示，忘了找服务商重置。</div>

  <h2>我的账号</h2>
  <div class="kv">
    <div><div class="k">账号状态</div><div class="v">{{ $user->statusLabel() }}</div></div>
    <div><div class="k">到期时间</div><div class="v">{{ $user->expires_at?->format('Y-m-d H:i') ?? '不限' }}</div></div>
    <div><div class="k">流量配额</div><div class="v">{{ $user->traffic_quota_bytes ? \App\Models\NodeUserTraffic::human($user->traffic_quota_bytes) : '不限' }}</div></div>
    <div><div class="k">已用流量</div><div class="v">{{ \App\Models\NodeUserTraffic::human((int) (($myTraffic->tx ?? 0) + ($myTraffic->rx ?? 0))) }}</div></div>
    <div><div class="k">用户 ID</div><div class="v"><code>{{ $user->uuid }}</code></div></div>
    <div><div class="k">当前在线</div><div class="v">
      @if ($myOnline->isEmpty())
        无
      @else
        {{ $myOnline->sum('connections') }} 个连接
      @endif
    </div></div>
  </div>

  <h2>可用服务器（{{ count($servers) }} 台）</h2>
  @if (count($servers) === 0)
    <div class="empty">服务商暂时没有可用服务器，稍后再试或联系服务商。</div>
  @else
    <table>
      <thead>
        <tr>
          <th>服务器</th>
          <th>状态</th>
          <th class="num">在线人数</th>
          <th class="num">连接数</th>
          <th>最后心跳</th>
          <th>版本</th>
        </tr>
      </thead>
      <tbody>
      @foreach ($servers as $server)
        <tr>
          <td>
            <div class="who">{{ $server['name'] }}</div>
            <div class="addr">{{ $server['host'] }}:{{ $server['port'] }}</div>
          </td>
          <td class="{{ $server['status_color'] }} t"><span class="dot"></span>{{ $server['status_label'] }}</td>
          <td class="num">{{ $server['online_users'] }}</td>
          <td class="num">{{ $server['connections'] }}</td>
          <td>
            {{ $server['last_seen_human'] }}
            @if ($server['last_seen'])
              <div class="addr">{{ $server['last_seen']->format('Y-m-d H:i:s') }}</div>
            @endif
          </td>
          <td class="addr">{{ $server['version'] }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>

    <h2>客户端配置</h2>
    <div class="hint">任选一台（建议选「在线」的），复制到 config.yaml；混淆密码是本运营商标识。</div>
    @foreach ($servers as $server)
      <details @if ($loop->first) open @endif>
        <summary>{{ $server['name'] }} · {{ $server['host'] }}（{{ $server['status_label'] }}）</summary>
        <pre>{{ $server['yaml'] }}</pre>
      </details>
    @endforeach
  @endif

  <h2>说明</h2>
  <div class="hint">
    在线人数 / 连接数来自最近一轮采集（约 30 秒一次），最后心跳超过
    {{ (int) config('hysteria.node_online_ttl', 90) }} 秒视为离线。<br>
    证书是自签的，所以配置里 <code>insecure: true</code>；有正式域名证书时可改成 <code>false</code>。<br>
    同一账号可在多台设备同时连接，连接数会体现在上表的「连接数」里。
  </div>
</div>
</body>
</html>
