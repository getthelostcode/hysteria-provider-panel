<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 终端用户：由各运营商的 /join/{code} 页面自助注册，归属该运营商。
        // uuid 就是 Hysteria 侧的 user id（流量统计、踢人都用它）。
        Schema::create('end_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->char('uuid', 32)->unique();
            $table->string('email', 190)->unique();
            $table->string('name', 190)->nullable();
            $table->string('password');
            $table->string('status', 20)->default('active')->index(); // active|disabled
            $table->unsignedBigInteger('traffic_quota_bytes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_auth_at')->nullable();
            $table->string('register_ip', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_users');
    }
};
