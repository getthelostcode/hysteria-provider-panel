@if (session('signup_success'))
    <div class="fi-slot" style="padding: 12px 16px 0;">
        <div class="rounded-lg bg-success-50 px-4 py-3 text-sm text-success-800 ring-1 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400">
            {{ session('signup_success') }} —— 先到「配置模板」看清识别码用法，再复制一键安装命令装节点。
        </div>
    </div>
@endif
