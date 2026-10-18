<x-filament-panels::page>
    <x-filament::section>
        <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">当前版本</div>
                <div class="mt-0.5 font-semibold">v{{ $version }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">最近保存</div>
                <div class="mt-0.5 font-semibold">{{ $updatedAt }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">保存人</div>
                <div class="mt-0.5 font-semibold">{{ $updatedBy }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">SHA256</div>
                <div class="mt-0.5 font-mono text-xs" title="{{ $sha256 }}">{{ substr($sha256, 0, 16) }}…</div>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            节点拉取地址：<span class="font-mono">{{ $endpoint }}</span>
            （install.sh 用 <span class="font-mono">TEMPLATE_URL</span> 指定，失败会回退到仓库自带模板）
        </p>
    </x-filament::section>

    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}

        <div class="flex flex-wrap gap-2">
            <x-filament::button type="submit">
                保存并递增版本
            </x-filament::button>
            <x-filament::button color="gray" wire:click="resetToDefault" type="button">
                恢复默认内容
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
