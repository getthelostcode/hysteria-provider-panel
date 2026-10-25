<x-filament-panels::page>
    <x-filament::section
        heading="给新用户一个默认套餐"
        description="比如「10 GB / 7 天」：用户注册后每 7 天把本期用量清零，超过 10 GB 鉴权就拒绝，直到下个周期。"
    >
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex items-center gap-3">
                <x-filament::button type="submit">保存默认套餐</x-filament::button>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    保存后只影响新注册用户；已有用户可在「用户」列表里逐个设置或清零。
                </span>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section heading="怎么生效">
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
            <p>· 用户在 <code class="font-mono">/join/&lt;你的识别码&gt;</code> 注册时，自动套用这里的配额与周期。</p>
            <p>· 用量由采集器按用户累计到「本期已用」，周期到了在下次鉴权或上报时自动清零。</p>
            <p>· 本期用完时 <code class="font-mono">/auth</code> 返回 <code class="font-mono">{"ok":false,"msg":"流量已用完，请联系服务商"}</code>。</p>
            <p>· 想立刻给某个用户重置：用户列表 →「清零本期用量」。</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
