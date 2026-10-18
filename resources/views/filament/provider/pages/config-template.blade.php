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

    {{-- 一键安装：页面中部、左对齐 --}}
    <div class="w-full" x-data="{ copied: false }">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">一键安装本运营商的节点</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            在节点（Linux，root）上粘贴执行；脚本会从面板拉当前生效的模板，渲染后安装 Hysteria 并自动注册。
        </p>

        <div class="mt-3 flex items-start gap-2">
            <pre class="flex-1 overflow-x-auto rounded-xl bg-gray-950 px-4 py-3 text-left text-sm text-gray-100">{{ $installCommand }}</pre>
            <x-filament::button
                color="gray"
                x-on:click="window.navigator.clipboard.writeText(@js($installCommand)); copied = true; setTimeout(() => copied = false, 1500)"
            >
                <span x-text="copied ? '已复制' : '复制'"></span>
            </x-filament::button>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            想先看会改什么：命令后加 <span class="font-mono">--dry-run</span>；
            只重新上报注册信息加 <span class="font-mono">--register-only</span>。
            @if ($scriptInfo)
                <br>脚本 sha256 <span class="font-mono">{{ substr($scriptInfo['sha256'], 0, 12) }}…</span>
                · 更新于 {{ $scriptInfo['updated_at'] }}
                · {{ number_format($scriptInfo['bytes'] / 1024, 1) }} KB
            @endif
        </p>

        <details class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            <summary class="cursor-pointer text-primary-600">先下载再执行（便于检查脚本）</summary>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-100 p-3 text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ $manualCommand }}</pre>
        </details>
    </div>

    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}

        <div class="flex flex-wrap gap-2">
            <x-filament::button type="submit">保存</x-filament::button>
            <x-filament::button color="gray" type="button" wire:click="useStandard">
                改回平台标准
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
