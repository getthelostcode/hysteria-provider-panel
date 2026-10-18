<div class="space-y-4" x-data="{ copied: false }">
    <div class="flex justify-center">
        <div class="rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
            {!! $qr !!}
        </div>
    </div>

    <div>
        <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">连接链接（hysteria2://）</div>
        <div class="flex items-start gap-2">
            <textarea readonly rows="3" class="w-full resize-none rounded-lg border border-gray-300 bg-gray-50 p-2 font-mono text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">{{ $uri }}</textarea>
            <x-filament::button
                color="gray"
                size="sm"
                x-on:click="window.navigator.clipboard.writeText(@js($uri)); copied = true; setTimeout(() => copied = false, 1500)"
            >
                <span x-text="copied ? '已复制' : '复制'"></span>
            </x-filament::button>
        </div>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        链接里已经带了你的专属凭据（uuid + token）与混淆密码，别公开分享；怀疑泄露可在服务商处重置。
    </p>
</div>
