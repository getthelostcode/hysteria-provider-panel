<?php

namespace App\Http\Controllers;

use App\Models\EndUser;
use App\Models\Provider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 每个运营商一个公开注册页：/join/{code}
 *
 * 用户注册后归属该运营商，登录凭据是 email + 自己设的密码，
 * Hysteria 通过面板 POST /auth 校验（返回 uuid 作为 user id）。
 */
class PublicJoinController
{
    public function show(string $code): View
    {
        $provider = $this->activeProviderOrFail($code);

        return view('join.form', [
            'provider' => $provider,
            'host' => config('hysteria.public_host'),
            'port' => config('hysteria.public_port'),
        ]);
    }

    public function store(Request $request, string $code): RedirectResponse
    {
        $provider = $this->activeProviderOrFail($code);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:190', 'unique:end_users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'email.unique' => '这个邮箱已经注册过了，请直接用原账号登录（忘记密码请联系服务商）。',
            'password.min' => '密码至少 8 位。',
            'password.confirmed' => '两次输入的密码不一致。',
        ]);

        $user = EndUser::query()->create([
            'provider_id' => $provider->id,
            'email' => mb_strtolower($data['email']),
            'name' => $data['name'] ?? null,
            'password' => $data['password'],
            'status' => EndUser::STATUS_ACTIVE,
            'register_ip' => $request->ip(),
        ]);

        $request->session()->put('join_user_id', $user->id);

        return redirect()->route('join.welcome', ['code' => $provider->code]);
    }

    public function welcome(Request $request, string $code): View|RedirectResponse
    {
        $provider = $this->activeProviderOrFail($code);

        $userId = $request->session()->get('join_user_id');
        $user = $userId ? EndUser::query()->where('provider_id', $provider->id)->find($userId) : null;

        if (! $user) {
            return redirect()->route('join.show', ['code' => $provider->code]);
        }

        return view('join.welcome', [
            'provider' => $provider,
            'user' => $user,
            'host' => config('hysteria.public_host'),
            'port' => config('hysteria.public_port'),
            'clientYaml' => $this->clientYaml($provider, $user),
        ]);
    }

    /** 注册成功后给用户复制的客户端配置（密码用占位符，不落日志/不回显明文） */
    private function clientYaml(Provider $provider, EndUser $user): string
    {
        $host = (string) config('hysteria.public_host');
        $port = (int) config('hysteria.public_port');

        return implode("\n", [
            'server: ' . $host . ':' . $port,
            'auth: ' . $user->email . ':<你设置的密码>',
            'obfs:',
            '  type: salamander',
            '  salamander:',
            '    password: ' . $provider->code,
            'tls:',
            '  sni: ' . $host,
            '  insecure: true',
        ]) . "\n";
    }

    private function activeProviderOrFail(string $code): Provider
    {
        $provider = Provider::query()->where('code', $code)->first();

        abort_if($provider === null || ! $provider->isActive(), 404);

        return $provider;
    }
}
