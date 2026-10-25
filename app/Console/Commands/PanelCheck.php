<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\InstallScriptController;
use App\Models\ConfigTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * 部署自检：php artisan panel:check
 *
 * 覆盖部署时最容易漏的几项：依赖装了没、路由在不在、令牌配了没、模板/脚本文件在不在。
 */
class PanelCheck extends Command
{
    protected $signature = 'panel:check';

    protected $description = '检查面板部署是否完整（依赖 / 路由 / 令牌 / 模板 / 安装脚本）';

    public function handle(): int
    {
        $rows = [];
        $failed = 0;

        $add = function (string $item, bool $ok, string $detail, bool $hard = true) use (&$rows, &$failed): void {
            $rows[] = [$ok ? '✅' : '❌', $item, $detail];
            if (! $ok && $hard) {
                $failed++;
            }
        };

        // 1. PHP / 依赖
        $add('PHP 版本', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION);
        $add('composer 依赖', class_exists(\Illuminate\Foundation\Application::class), 'vendor/autoload.php 可加载');
        $add('二维码库 endroid/qr-code', class_exists(\Endroid\QrCode\QrCode::class),
            class_exists(\Endroid\QrCode\QrCode::class) ? '已安装' : '缺依赖 → composer install --no-dev');

        // 2. 关键路由
        foreach ([
            'api.auth' => 'POST /auth（Hysteria 鉴权）',
            'api.install_script' => 'GET /api/install.sh（一键安装脚本）',
            'api.config_template' => 'GET /api/config-template（标准模板）',
            'api.collector.nodes' => 'GET /api/collector/v1/nodes（采集器）',
            'filament.user.pages.servers' => 'GET /user/servers（用户面板）',
            'filament.provider.pages.quota' => 'GET /provider/{code}/quota（套餐默认值）',
            'filament.provider.pages.config-template' => 'GET /provider/{code}/config-template（配置模板）',
        ] as $name => $label) {
            $add('路由 ' . $label, Route::has($name), Route::has($name) ? '已注册' : '缺失（config/route 缓存？跑 optimize:clear）');
        }

        // 3. 鉴权地址
        $authUrl = (string) config('hysteria.auth_url');
        $add('鉴权地址（节点侧）', $authUrl !== '', $authUrl !== '' ? $authUrl : '未配置 hysteria.auth_url',
            hard: false);

        // 4. 令牌
        $collectorToken = (string) config('collector.token');
        $add('COLLECTOR_TOKEN', $collectorToken !== '',
            $collectorToken !== '' ? '已配置（长度 ' . strlen($collectorToken) . '）' : '为空 → /api/collector/v1/* 会 503',
            hard: false);

        // 5. 数据库 / 迁移
        try {
            $add('数据库', true, (string) config('database.default'));
            $add('表 end_users', Schema::hasTable('end_users'), '终端用户');
            $add('列 end_users.quota_used_bytes', Schema::hasColumn('end_users', 'quota_used_bytes'),
                Schema::hasColumn('end_users', 'quota_used_bytes') ? '配额周期' : '缺列 → php artisan migrate --force');
            $add('列 providers.default_quota_bytes', Schema::hasColumn('providers', 'default_quota_bytes'),
                Schema::hasColumn('providers', 'default_quota_bytes') ? '新用户默认套餐' : '缺列 → php artisan migrate --force');
            $add('表 node_user_traffic', Schema::hasTable('node_user_traffic'), '每用户流量');
            $add('表 config_templates', Schema::hasTable('config_templates'), '标准模板');
        } catch (Throwable $e) {
            $add('数据库', false, $e->getMessage());
        }

        // 6. 模板 / 安装脚本
        try {
            $template = ConfigTemplate::standard();
            $add('标准模板', $template->version >= 1, 'v' . $template->version . '（' . strlen($template->content) . ' 字节）');
        } catch (Throwable $e) {
            $add('标准模板', false, $e->getMessage());
        }

        $scriptInfo = InstallScriptController::info();
        $add('install.sh', $scriptInfo !== null,
            $scriptInfo ? 'sha256 ' . substr($scriptInfo['sha256'], 0, 12) . '… · ' . $scriptInfo['updated_at'] : '缺 resources/hysteria/install.sh');

        // 7. 写权限
        $add('storage 可写', is_writable(storage_path()), storage_path());

        $this->table(['', '检查项', '结果'], $rows);

        if ($failed > 0) {
            $this->error("有 {$failed} 项需要处理（❌）");

            return self::FAILURE;
        }

        $this->info('全部通过 ✅');

        return self::SUCCESS;
    }
}
