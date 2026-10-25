<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 流量配额周期：运营商可给用户设「10 GB / 7 天」这类套餐，周期到了自动清零。
 *
 * - end_users.quota_used_bytes   本期已用（auth 判超额、用户中心展示都用它）
 * - end_users.quota_reset_days   重置周期（天）；空 = 不重置（一次性额度）
 * - end_users.quota_reset_at     下次重置时间；到期后由鉴权/采集写入时惰性清零
 * - providers.default_*          新注册用户默认套用的配额与周期
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->unsignedBigInteger('quota_used_bytes')->default(0)->after('traffic_quota_bytes');
            $table->unsignedSmallInteger('quota_reset_days')->nullable()->after('quota_used_bytes');
            $table->timestamp('quota_reset_at')->nullable()->after('quota_reset_days');
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->unsignedBigInteger('default_quota_bytes')->nullable()->after('contact');
            $table->unsignedSmallInteger('default_quota_reset_days')->nullable()->after('default_quota_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn(['quota_used_bytes', 'quota_reset_days', 'quota_reset_at']);
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['default_quota_bytes', 'default_quota_reset_days']);
        });
    }
};
