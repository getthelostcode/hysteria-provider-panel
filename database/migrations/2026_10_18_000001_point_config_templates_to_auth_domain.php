<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 把已存库的模板里的鉴权地址从面板域名切到独立鉴权服务（auth.ipx.sarl）。
 *
 * 只替换旧默认值；服务商自己写死别的地址不动。改过内容的模板 version 自增，
 * 节点下次拉模板会看到新版本。
 */
return new class extends Migration
{
    private const OLD = 'https://hysteria.ipx.sarl/auth';

    public function up(): void
    {
        $new = (string) config('hysteria.auth_url', 'https://auth.ipx.sarl/auth');
        if ($new === '' || $new === self::OLD) {
            return;
        }

        foreach (['config_templates', 'provider_config_templates'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where('content', 'like', '%' . self::OLD . '%')
                ->orderBy('id')
                ->each(function (object $row) use ($table, $new): void {
                    $content = str_replace(self::OLD, $new, (string) $row->content);

                    $update = ['content' => $content];
                    if (Schema::hasColumn($table, 'version')) {
                        $update['version'] = (int) $row->version + 1;
                    }
                    if (Schema::hasColumn($table, 'updated_at')) {
                        $update['updated_at'] = now();
                    }

                    DB::table($table)->where('id', $row->id)->update($update);
                });
        }
    }

    public function down(): void
    {
        $new = (string) config('hysteria.auth_url', 'https://auth.ipx.sarl/auth');

        foreach (['config_templates', 'provider_config_templates'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where('content', 'like', '%' . $new . '%')
                ->orderBy('id')
                ->each(function (object $row) use ($table): void {
                    $content = str_replace($new, self::OLD, (string) $row->content);

                    $update = ['content' => $content];
                    if (Schema::hasColumn($table, 'version')) {
                        $update['version'] = max(1, (int) $row->version - 1);
                    }

                    DB::table($table)->where('id', $row->id)->update($update);
                });
        }
    }
};
