<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            // 节点 TLS 证书指纹：register-hysteria 安装时上报
            $table->string('cert_sha256', 95)->nullable()->after('traffic_secret');      // AB:CD:…（hex，给人核对）
            $table->string('cert_pin_sha256', 64)->nullable()->after('cert_sha256');     // base64(SHA256(DER))，客户端 pinSHA256
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['cert_sha256', 'cert_pin_sha256']);
        });
    }
};
