<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>服务商注册 · {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #0b1220;
            --card: #111a2b;
            --line: #223049;
            --text: #e6edf7;
            --muted: #94a3b8;
            --accent: #10b981;
            --accent-dark: #059669;
            --danger: #f87171;
            --danger-bg: #3f1d1d;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 32px 16px; background: radial-gradient(1200px 600px at 50% -10%, #12303a 0%, var(--bg) 55%);
            color: var(--text); font: 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
        }
        .wrap { width: 100%; max-width: 520px; }
        .brand { text-align: center; margin-bottom: 18px; }
        .brand h1 { margin: 0 0 4px; font-size: 22px; letter-spacing: .3px; }
        .brand p { margin: 0; color: var(--muted); font-size: 13px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 26px; box-shadow: 0 20px 60px rgba(0,0,0,.35); }
        .row { display: grid; gap: 14px; }
        .grid-2 { display: grid; gap: 14px; grid-template-columns: 1fr 1fr; }
        label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 6px; }
        .hint { font-size: 12px; color: var(--muted); margin-top: 5px; }
        input {
            width: 100%; padding: 11px 12px; border-radius: 10px; border: 1px solid var(--line);
            background: #0d1524; color: var(--text); font-size: 14px; outline: none;
        }
        input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(16,185,129,.15); }
        .err { border-color: var(--danger) !important; }
        .error-text { color: var(--danger); font-size: 12px; margin-top: 5px; }
        .alert {
            background: var(--danger-bg); border: 1px solid #7f1d1d; color: #fecaca;
            border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 16px;
        }
        button {
            width: 100%; margin-top: 6px; padding: 12px 16px; border: 0; border-radius: 10px;
            background: linear-gradient(180deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: #04261b; font-weight: 700; font-size: 15px; cursor: pointer;
        }
        button:hover { filter: brightness(1.05); }
        .foot { margin-top: 16px; text-align: center; font-size: 13px; color: var(--muted); }
        .foot a { color: var(--accent); text-decoration: none; }
        .foot a:hover { text-decoration: underline; }
        @media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } .card { padding: 20px; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <h1>注册服务商</h1>
        <p>开通后即可登录服务商后台：管节点、看流量、开终端用户</p>
    </div>

    <div class="card">
        @if ($errors->any())
            <div class="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ url('/provider-signup') }}" class="row" autocomplete="on">
            @csrf

            <div>
                <label for="name">运营商名称</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="120"
                       placeholder="例如：星云网络" class="{{ $errors->has('name') ? 'err' : '' }}">
            </div>

            <div>
                <label for="code">识别码（可留空自动生成）</label>
                <input id="code" name="code" value="{{ old('code') }}" maxlength="32"
                       placeholder="例如：xingyun01" class="{{ $errors->has('code') ? 'err' : '' }}">
                <div class="hint">小写字母/数字/-/_；同时用作节点混淆密码与用户注册页 <code>/join/&lt;识别码&gt;</code>，开通后尽量别改。</div>
            </div>

            <div class="grid-2">
                <div>
                    <label for="email">登录账号（邮箱）</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="190"
                           placeholder="you@example.com" class="{{ $errors->has('email') ? 'err' : '' }}">
                </div>
                <div>
                    <label for="contact">联系方式（选填）</label>
                    <input id="contact" name="contact" value="{{ old('contact') }}" maxlength="190" placeholder="TG / 微信 / 电话">
                </div>
            </div>

            <div class="grid-2">
                <div>
                    <label for="password">登录密码</label>
                    <input id="password" type="password" name="password" required minlength="8"
                           autocomplete="new-password" placeholder="至少 8 位" class="{{ $errors->has('password') ? 'err' : '' }}">
                </div>
                <div>
                    <label for="password_confirmation">确认密码</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8"
                           autocomplete="new-password" placeholder="再输一次">
                </div>
            </div>

            <button type="submit">注册并进入后台</button>
        </form>

        <div class="foot">
            已有账号？<a href="{{ url('/provider/login') }}">直接登录</a>
        </div>
    </div>
</div>
</body>
</html>
