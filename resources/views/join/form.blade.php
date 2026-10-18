<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $provider->name }} · 注册</title>
<style>
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
         font: 15px/1.6 -apple-system, "Segoe UI", "Noto Sans SC", sans-serif;
         background: radial-gradient(1200px 600px at 50% -10%, #d1fae5 0%, #f8fafc 55%); color: #0f172a; padding: 24px; }
  .card { width: 100%; max-width: 440px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
          box-shadow: 0 12px 32px rgba(15, 23, 42, .08); padding: 28px; }
  h1 { margin: 0 0 4px; font-size: 20px; }
  .sub { color: #64748b; font-size: 13px; margin-bottom: 20px; }
  label { display: block; font-size: 13px; color: #334155; margin: 14px 0 6px; }
  input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 9px; font: inherit; }
  input:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, .15); }
  button { width: 100%; margin-top: 20px; padding: 11px 14px; border: 0; border-radius: 9px; cursor: pointer;
           background: #059669; color: #fff; font: inherit; font-weight: 600; }
  button:hover { background: #047857; }
  .err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 9px;
         padding: 10px 12px; font-size: 13px; margin-bottom: 6px; }
  .err ul { margin: 0; padding-left: 18px; }
  .meta { margin-top: 18px; padding-top: 14px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 12px; }
  code { background: #f1f5f9; padding: 1px 5px; border-radius: 5px; }
</style>
</head>
<body>
<div class="card">
  <h1>{{ $provider->name }}</h1>
  <div class="sub">注册一个账号，用于连接本服务商的 Hysteria 服务</div>

  @if ($errors->any())
    <div class="err">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('join.store', ['code' => $provider->code]) }}">
    @csrf
    <label for="name">昵称（可选）</label>
    <input id="name" name="name" value="{{ old('name') }}" maxlength="60" autocomplete="nickname">

    <label for="email">邮箱（登录账号）</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username">

    <label for="password">密码（至少 8 位）</label>
    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">

    <label for="password_confirmation">再输一次密码</label>
    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">

    <button type="submit">注册</button>
  </form>

  <div class="meta">
    服务器 <code>{{ $host }}:{{ $port }}</code> · 标识 <code>{{ $provider->code }}</code><br>
    已有账号？直接在客户端用「邮箱 + 密码」登录即可，无需再来此页。
  </div>
</div>
</body>
</html>
