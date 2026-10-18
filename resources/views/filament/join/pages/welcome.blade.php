<x-filament-panels::page>
    <x-filament::section heading="我的账号" description="登录客户端用邮箱 + 你注册时设置的密码">
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">账号状态</dt>
                <dd class="mt-0.5 font-semibold">{{ $user->statusLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">到期时间</dt>
                <dd class="mt-0.5 font-semibold">{{ $this->expiresLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">流量配额</dt>
                <dd class="mt-0.5 font-semibold">{{ $this->quotaLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">已用流量</dt>
                <dd class="mt-0.5 font-semibold">{{ $this->usedLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">当前在线</dt>
                <dd class="mt-0.5 font-semibold">{{ $myConnections }} 个连接</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">用户 ID</dt>
                <dd class="mt-0.5 font-mono text-xs">{{ $user->uuid }}</dd>
            </div>
        </dl>
        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            登录账号：<span class="font-mono">{{ $user->email }}</span>
            ｜ 混淆密码（Salamander）：<span class="font-mono">{{ $provider->code }}</span>
            ｜ 服务器端口：{{ (int) config('hysteria.public_port', 443) }}
        </p>
    </x-filament::section>

    <x-filament::section
        heading="可用服务器"
        description="每 30 秒刷新一次；最后心跳超过 {{ (int) config('hysteria.node_online_ttl', 90) }} 秒视为离线"
    >
        {{ $this->table }}

        <div class="mt-4 flex flex-wrap gap-2">
            <x-filament::button tag="a" href="{{ url('/user/login') }}" size="sm">
                登录用户中心查看历史用量
            </x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section heading="客户端怎么用">
        <div class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
            <p>任选一台「在线」的服务器 → 点「客户端配置」→ 复制到客户端 <code class="rounded bg-gray-100 px-1.5 py-0.5 dark:bg-gray-800">config.yaml</code>。</p>
            <p>证书是自签的，配置里 <code class="rounded bg-gray-100 px-1.5 py-0.5 dark:bg-gray-800">insecure: true</code>；有正式证书可改为 <code class="rounded bg-gray-100 px-1.5 py-0.5 dark:bg-gray-800">false</code>。</p>
            <p>同一账号可在多台设备同时连接，连接数会体现在上表。</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
