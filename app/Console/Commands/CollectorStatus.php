<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 排查采集器接口 401/503 用：打印"运行时实际读到的 token"来自哪里。
 *
 *   php artisan collector:status
 */
class CollectorStatus extends Command
{
    protected $signature = 'collector:status';

    protected $description = '检查采集器接口（/api/collector/v1/*）的 COLLECTOR_TOKEN 是否已生效';

    public function handle(): int
    {
        $token = (string) config('collector.token');
        $envFile = app()->environmentFilePath();
        $envToken = $this->tokenFromEnvFile($envFile);

        $cachedConfig = base_path('bootstrap/cache/config.php');

        $this->table(['项', '值'], [
            ['APP_ENV', app()->environment()],
            ['环境文件（运行时读取）', $envFile . (is_file($envFile) ? '' : '（不存在！）')],
            ['该文件里的 COLLECTOR_TOKEN', $envToken === '' ? '（空/未配置）' : sprintf('已配置，长度 %d', strlen($envToken))],
            ['config(collector.token)', $token === '' ? '（空）' : sprintf('已加载，长度 %d', strlen($token))],
            ['config 缓存文件', is_file($cachedConfig) ? $cachedConfig . '（' . date('Y-m-d H:i:s', filemtime($cachedConfig)) . '）' : '无（未缓存，运行时读 .env）'],
            ['路由缓存文件', is_file(base_path('bootstrap/cache/routes-v7.php')) ? '有' : '无'],
        ]);

        if ($token !== '') {
            $this->info('✅ 接口令牌已生效：/api/collector/v1/* 用 X-Collector-Token 可以访问');

            return self::SUCCESS;
        }

        $this->error('❌ 运行时 token 为空，接口会返回 503 collector_api_disabled');

        if ($envToken !== '') {
            $this->warn('原因：.env 里有值，但 config 缓存是旧的 —— 执行：');
            $this->line('   php artisan config:clear && php artisan config:cache');
        } elseif (! is_file($envFile)) {
            $this->warn('原因：运行时环境文件不存在，检查 APP_ENV 与 .env 文件名（可能是 .env.production）');
        } else {
            $this->warn('原因：环境文件里没有 COLLECTOR_TOKEN，执行：');
            $this->line('   echo "COLLECTOR_TOKEN=$(openssl rand -hex 24)" >> .env && php artisan config:cache');
        }

        return self::FAILURE;
    }

    private function tokenFromEnvFile(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^\s*COLLECTOR_TOKEN\s*=\s*(.*)$/', $line, $m)) {
                return trim($m[1], " \"'");
            }
        }

        return '';
    }
}
