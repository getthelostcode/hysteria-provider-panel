{{-- 复制按钮：文本放在 x-data 里（单引号属性），避免 @js 的双引号截断属性；HTTPS 外用 execCommand 降级 --}}
<div
    x-data='{
        copied: false,
        text: @js($uri),
        copy() {
            const done = () => {
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 1500);
            };
                        const fallback = () => {
                const el = document.createElement("textarea");
                el.value = this.text;
                el.setAttribute("readonly", "");
                el.style.position = "fixed";
                el.style.opacity = "0";
                document.body.appendChild(el);
                el.select();
                try { document.execCommand("copy"); } catch (e) {}
                document.body.removeChild(el);
                done();
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(this.text).then(done).catch(fallback);
            } else {
                fallback();
            }
        }
    }'
    class="space-y-4"
>
    @if (! empty($qr))
        <div class="flex justify-center">
            <div class="rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                {!! $qr !!}
            </div>
        </div>
    @else
        <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
            二维码生成失败（服务端缺少 <span class="font-mono">endroid/qr-code</span>，在面板目录跑
            <span class="font-mono">composer install --no-dev</span>）。
            下面的链接仍然可用，复制到客户端即可。
        </div>
    @endif

    <div>
        <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">连接链接（hysteria2://）</div>
        <div class="flex items-start gap-2">
            <textarea
                id="connect-uri"
                readonly
                rows="3"
                class="w-full resize-none rounded-lg border border-gray-300 bg-gray-50 p-2 font-mono text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                x-on:click="$el.select()"
            >{{ $uri }}</textarea>

            <x-filament::button type="button" color="gray" size="sm" x-on:click="copy()">
                <span x-text="copied ? '已复制' : '复制'"></span>
            </x-filament::button>
        </div>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        链接里已经带了你的专属凭据（uuid）与混淆密码，别公开分享；怀疑泄露可在服务商处重置 UUID。
    </p>
</div>
