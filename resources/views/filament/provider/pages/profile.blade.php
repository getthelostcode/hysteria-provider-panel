<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit">保存</x-filament::button>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                名称全局唯一；改邮箱后用新邮箱登录；改密码需要当前密码。
            </span>
        </div>
    </form>

    <x-filament::section heading="说明">
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
            <p>· 运营商名称会显示在服务商后台、用户注册页和用户中心，所有服务商不能重名。</p>
            <p>· 登录邮箱同时是服务商后台账号，可在上面直接修改（不能与其他账号重复）。</p>
            <p>· 识别码（{{ $this->provider()->code }}）是租户主键，创建后不可修改。</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
