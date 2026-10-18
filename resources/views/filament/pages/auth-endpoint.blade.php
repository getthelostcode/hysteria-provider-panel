<x-filament-panels::page>
    <x-filament::section heading="接口信息">
        <div class="grid gap-4 text-sm sm:grid-cols-3">
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">地址（POST）</div>
                <div class="mt-0.5 break-all font-mono text-xs">{{ $endpoint }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">用户数 / 启用</div>
                <div class="mt-0.5 font-semibold">{{ $users }} / {{ $activeUsers }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">限流</div>
                <div class="mt-0.5 font-semibold">600 次 / 分钟 / IP</div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">节点侧配置</div>
                <pre class="overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $nodeConfig }}</pre>
            </div>
            <div>
                <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">请求体</div>
                <pre class="overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $sampleRequest }}</pre>
            </div>
        </div>

        <div class="mt-4">
            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">curl 自测</div>
            <pre class="overflow-x-auto rounded-lg bg-gray-100 p-3 text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ $sampleCurl }}</pre>
        </div>
    </x-filament::section>

    <x-filament::section
        heading="在线自测"
        description="向上面配置的地址真发一次请求：留空 = 只验证可达；只有账号 = uuid 直连模式；账号+密码 = 邮箱密码或 uuid:token"
    >
        <form wire:submit="test" class="space-y-4">
            {{ $this->form }}

            <x-filament::button type="submit">发送到 /auth</x-filament::button>
        </form>

        @if ($result)
            <div class="mt-4">
                <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">返回</div>
                <pre class="overflow-x-auto rounded-lg p-3 text-xs {{ ($result['ok'] ?? false) ? 'bg-green-950 text-green-100' : 'bg-red-950 text-red-100' }}">{{ json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="鉴权规则">
        <div class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
            <p>认四种 auth：<code class="font-mono">uuid</code>（二维码/链接）、<code class="font-mono">uuid:uuid</code>、
                <code class="font-mono">邮箱:密码</code>、<code class="font-mono">uuid:connect_token</code>。</p>
            <p>成功返回 <code class="font-mono">{"ok":true,"id":"&lt;uuid&gt;"}</code>，id 即流量统计与踢人用的用户 ID。</p>
            <p>账号停用 / 到期 / 运营商停用时返回 <code class="font-mono">{"ok":false,"msg":"…"}</code>。</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
