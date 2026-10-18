<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 服务商自定义的 config.yaml（不填则用平台标准模板）
        Schema::create('provider_config_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->string('updated_by', 190)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_config_templates');
    }
};
