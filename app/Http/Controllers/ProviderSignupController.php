<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\ProviderUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 服务商自助注册：填运营商信息 + 登录账号/密码，建好直接进服务商后台。
 *
 * 关掉入口：.env 里 PROVIDER_SIGNUP_ENABLED=false（或 config('hysteria.provider_signup_enabled')）。
 */
class ProviderSignupController extends Controller
{
    public function show(): View
    {
        $this->ensureEnabled();

        return view('provider.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        // 识别码统一小写再校验（用户手输 XingYun01 也能过）
        $request->merge(['code' => Str::lower(trim((string) $request->input('code', '')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'code' => ['nullable', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:190', 'confirmed'],
            'contact' => ['nullable', 'string', 'max:190'],
        ], [
            'name.required' => '请填运营商名称',
            'name.min' => '运营商名称至少 2 个字',
            'code.regex' => '识别码只能用小写字母、数字、- 和 _，且以字母或数字开头',
            'code.min' => '识别码至少 3 位',
            'email.required' => '请填登录邮箱',
            'email.email' => '登录邮箱格式不对',
            'password.required' => '请设置登录密码',
            'password.min' => '密码至少 8 位',
            'password.confirmed' => '两次输入的密码不一致',
        ]);

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = 'p' . Str::lower(Str::random(10));
        }

        if (Provider::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => '这个识别码已被占用，换一个']);
        }
        if (ProviderUser::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => '这个邮箱已经注册过服务商']);
        }

        $provider = DB::transaction(function () use ($data, $code): Provider {
            $provider = Provider::query()->create([
                'code' => $code,
                'name' => trim($data['name']),
                'contact' => $data['contact'] ?? null,
                'status' => Provider::STATUS_ACTIVE,
                'notes' => '自助注册于 ' . now()->toDateTimeString(),
            ]);

            ProviderUser::query()->create([
                'provider_id' => $provider->id,
                'name' => trim($data['name']),
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            return $provider;
        });

        $user = $provider->providerUsers()->where('email', $data['email'])->firstOrFail();

        Auth::guard('provider')->login($user);
        $request->session()->regenerate();

        return redirect("/provider/{$provider->code}")
            ->with('signup_success', "注册成功，识别码：{$provider->code}");
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) config('hysteria.provider_signup_enabled', true), 404);
    }
}
