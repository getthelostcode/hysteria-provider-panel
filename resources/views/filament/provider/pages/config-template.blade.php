<x-filament-panels::page>
    <x-filament::section>
        <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">当前生效</div>
                <div class="mt-0.5 font-semibold">
                    {{ $effective['source_label'] }} v{{ $effective['version'] }}
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">平台标准版</div>
                <div class="mt-0.5 font-semibold">v{{ $standardVersion }}</div>
                <div class="text-xs text-gray-500">{{ $standardUpdatedAt }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">SHA256</div>
                <div class="mt-0.5 font-mono text-xs" title="{{ $effective['sha256'] }}">
                    {{ substr($effective['sha256'], 0, 16) }}…
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">节点拉取地址</div>
                <div class="mt-0.5 break-all font-mono text-xs">{{ $endpoint }}</div>
            </div>
        </div>
        @if ($hasOverride)
            <p class="mt-3 text-xs text-warning-600 dark:text-warning-400">
                正在使用本运营商的自定义模板；清空下面内容并保存即可回到平台标准版。
            </p>
        @else
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                当前使用平台标准模板。在下面填写内容并保存，即可为本运营商定制。
            </p>
        @endif
    </x-filament::section>

    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}

        <div class="flex flex-wrap gap-2">
            <x-filament::button type="submit">保存</x-filament::button>
            <x-filament::button color="gray" type="button" wire:click="useStandard">
                改回平台标准
            </x-filament::button>
        </div>
    </form>

    <x-filament::section
        heading="一键安装命令"
        description="在节点上以 root 执行（脚本会从面板拉本运营商的模板，渲染后安装并注册）"
    >
        <div class="space-y-3">
            <div
                class="flex items-start gap-2"
                x-data="{ copied: false }"
            >
                <pre class="flex-1 overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $installCommand }}</pre>
                <x-filament::button
                    color="gray"
                    size="sm"
                    x-on:click="window.navigator.clipboard.writeText(@js($installCommand)); copied = true; setTimeout(() => copied = false, 1500)"
                >
                    <span x-text="copied ? '已复制' : '复制'"></span>
                </x-filament::button>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                或者 clone 后执行（便于查看脚本、重跑）：
            </p>
            <pre class="overflow-x-auto rounded-lg bg-gray-100 p-3 text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ $manualCommand }}</pre>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                常用附加参数：<span class="font-mono">--dry-run</span>（只看渲染结果）、
                <span class="font-mono">--register-only</span>（只重新上报）、
                <span class="font-mono">--template-file /path/config.yaml</span>（离线用本地模板）。
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
