<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-filament::section>
            <div class="text-xs text-gray-500 dark:text-gray-400">累计流量</div>
            <div class="mt-1 text-2xl font-semibold">{{ \App\Models\NodeUserTraffic::human($totalBytes) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500 dark:text-gray-400">今日流量</div>
            <div class="mt-1 text-2xl font-semibold">{{ \App\Models\NodeUserTraffic::human($todayBytes) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500 dark:text-gray-400">当前连接</div>
            <div class="mt-1 text-2xl font-semibold">{{ $connections }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500 dark:text-gray-400">可用服务器</div>
            <div class="mt-1 text-2xl font-semibold">{{ $nodes }}</div>
        </x-filament::section>
    </div>

    <x-filament::section heading="账号">
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">邮箱</dt>
                <dd class="mt-0.5 font-mono text-sm">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">状态</dt>
                <dd class="mt-0.5 font-semibold">{{ $user->statusLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">到期时间</dt>
                <dd class="mt-0.5 font-semibold">{{ $expiresLabel }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">流量配额</dt>
                <dd class="mt-0.5 font-semibold {{ $quotaExceeded ? 'text-danger-600 dark:text-danger-400' : '' }}">
                    {{ $quotaLabel }}
                </dd>
                <dd class="text-xs text-gray-500 dark:text-gray-400">
                    本期 {{ $quotaUsageLabel }}
                    @if ($quotaResetLabel !== '不重置')
                        · {{ $quotaResetLabel }} 重置
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">用户 ID</dt>
                <dd class="mt-0.5 font-mono text-xs">{{ $user->uuid }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">运营商</dt>
                <dd class="mt-0.5 font-semibold">{{ $provider->name }}</dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section heading="怎么连" description="到「服务器」页点「连接 / 二维码」，扫码或复制链接即可；链接里的账号/密码就是你的用户 ID">
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
            <p>服务器：<span class="font-mono">{{ $provider->name }}</span> 下的任一台，见「服务器」页。</p>
            <p>混淆密码（Salamander）：<span class="font-mono">{{ $provider->code }}</span></p>
            <p>端口：<span class="font-mono">{{ (int) config('hysteria.public_port', 443) }}</span></p>
            <div class="flex flex-wrap gap-2 pt-2">
                <x-filament::button tag="a" href="{{ \App\Filament\User\Pages\Usage::getUrl() }}" color="gray" size="sm">
                    看使用记录
                </x-filament::button>
                <x-filament::button tag="a" href="{{ \App\Filament\User\Pages\Servers::getUrl() }}" size="sm">
                    看服务器
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
