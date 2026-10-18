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
  .card { width: 100%; max-width: 560px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
          box-shadow: 0 12px 32px rgba(15, 23, 42, .08); padding: 28px; }
  h1 { margin: 0 0 4px; font-size: 20px; }
  .sub { color: #64748b; font-size: 13px; margin-bottom: 20px; }
  .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; border-radius: 9px;
        padding: 10px 12px; font-size: 13px; margin-bottom: 18px; }
  table { width: 100%; border-collapse: collapse; margin: 8px 0 18px; font-size: 14px; }
  td { padding: 7px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
  td:first-child { color: #64748b; width: 96px; white-space: nowrap; }
  code, pre { font-family: ui-monospace, Menlo, Consolas, monospace; }
  code { background: #f1f5f9; padding: 2px 6px; border-radius: 5px; font-size: 13px; }
  pre { background: #0f172a; color: #e2e8f0; padding: 14px 16px; border-radius: 10px; overflow-x: auto; font-size: 12.5px; }
  h2 { font-size: 14px; margin: 22px 0 8px; color: #334155; }
  .hint { color: #64748b; font-size: 12px; }
  a.btn { display: inline-block; margin-top: 10px; padding: 9px 14px; border-radius: 9px; background: #059669;
          color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; }
</style>
</head>
<body>
<div class="card">
  <h1>注册成功</h1>
  <div class="sub">{{ $provider->name }} · 账号已创建，可以立即在客户端使用</div>

  <div class="ok">请把下面「服务器 / 账号 / 密码」填进客户端；密码就是你刚才设置的，页面不再显示。</div>

  <table>
    <tr><td>服务器</td><td><code>{{ $host }}:{{ $port }}</code></td></tr>
    <tr><td>登录账号</td><td><code>{{ $user->email }}</code></td></tr>
    <tr><td>密码</td><td>你刚才设置的那个密码</td></tr>
    <tr><td>混淆密码</td><td><code>{{ $provider->code }}</code>（Salamander，客户端选 obfs）</td></tr>
    <tr><td>用户 ID</td><td><code>{{ $user->uuid }}</code><span class="hint">（排障用，客户端不需要填）</span></td></tr>
  </table>

  <h2>客户端配置（复制到 config.yaml）</h2>
  <pre>{{ $clientYaml }}</pre>
  <div class="hint">证书是自签的，所以 <code>insecure: true</code>；有域名证书时可改成 <code>false</code> 并填 <code>sni</code>。</div>

  <a class="btn" href="{{ route('join.show', ['code' => $provider->code]) }}">返回注册页</a>
</div>
</body>
</html>
