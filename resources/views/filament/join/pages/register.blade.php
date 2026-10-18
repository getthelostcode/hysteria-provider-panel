<x-filament-panels::page>
    <form wire:submit="create" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" class="w-full">
            注册
        </x-filament::button>
    </form>

    <x-filament::section>
        <x-slot name="heading">说明</x-slot>
        <div class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
            <p>注册后用「邮箱 + 你设置的密码」在客户端登录，无需再来此页。</p>
            <p>混淆密码是本运营商标识：<code class="rounded bg-gray-100 px-1.5 py-0.5 dark:bg-gray-800">{{ $provider->code }}</code></p>
            <p>已有账号？<a class="font-semibold text-primary-600 underline" href="{{ url('/user/login') }}">登录用户中心</a> 查看用量；忘记密码请联系服务商重置。</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
