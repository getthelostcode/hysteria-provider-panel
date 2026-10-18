<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 标准版 Hysteria config.yaml：平台后台可编辑保存，节点安装时从这里拉取
        Schema::create('config_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->string('updated_by', 190)->nullable();
            $table->timestamps();
        });

        // 节点安装时用的模板版本（注册 payload 上报，便于核对"是不是同一份标准模板"）
        Schema::table('nodes', function (Blueprint $table) {
            $table->unsignedInteger('config_version')->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('config_version');
        });

        Schema::dropIfExists('config_templates');
    }
};
