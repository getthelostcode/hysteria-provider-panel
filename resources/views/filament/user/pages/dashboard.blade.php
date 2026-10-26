<x-filament-panels::page>
    <style>
        .sh-cards { display: grid; gap: 16px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
        @media (max-width: 1100px) { .sh-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px)  { .sh-cards { grid-template-columns: 1fr; } }
        .sh-bar { height: 10px; margin-top: 12px; border-radius: 9999px; overflow: hidden; background: rgba(148, 163, 184, .25); }
        .sh-bar > span { display: block; height: 100%; border-radius: 9999px; transition: width .3s ease; }
        .sh-meta { display: flex; flex-wrap: wrap; gap: 4px 16px; margin-top: 8px; font-size: 12px; color: #6b7280; }
        .sh-account { display: grid; gap: 16px 24px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
        @media (max-width: 1100px) { .sh-account { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px)  { .sh-account { grid-template-columns: 1fr; } }
        .dark .sh-meta { color: #9ca3af; }
    </style>

    <div class="sh-cards">
        <x-filament::section>
            <div class="text-xs text-gray-500 dark:text-gray-400">累计流量</div>
            <div class="mt-1 text-2xl font-semibold">{{ \App\Support\Bytes::human($totalBytes) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500 dark:text-gray-400">今日流量</div>
            <div class="mt-1 text-2xl font-semibold">{{ \App\Support\Bytes::human($todayBytes) }}</div>
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

    <x-filament::section
        heading="流量使用"
        description="{{ $quotaBytes !== null ? '本期配额使用比例 · 到重置时间自动清零' : '不限流量 · 以下为本期已用' }}"
    >
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div class="text-2xl font-semibold {{ $quotaExceeded ? 'text-danger-600 dark:text-danger-400' : '' }}">
                {{ $quotaUsageLabel }}
            </div>
            @if ($quotaPercent !== null)
                <div class="text-sm font-semibold" style="color: {{ $quotaBarHex }}">{{ $quotaPercent }}%</div>
            @endif
        </div>

        @if ($quotaPercent !== null)
            <div class="sh-bar">
                <span style="width: {{ $quotaPercent }}%; background-color: {{ $quotaBarHex }}"></span>
            </div>
            <div class="sh-meta">
                <span>剩余 {{ $quotaRemainingLabel }}</span>
                @if ($quotaResetLabel !== '不重置')
                    <span>下次重置 {{ $quotaResetLabel }}</span>
                @endif
                @if ($quotaExceeded)
                    <span class="font-semibold text-danger-600 dark:text-danger-400">本期已用完，连接会被拒绝</span>
                @endif
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="账号">
        <dl class="sh-account">
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
            <p>服务器：用「服务器」页里显示的 <span class="font-mono">IP</span>，任选一台即可（连接链接和二维码都以 IP 为准）。</p>
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
