<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            // 客户端连接凭据：hysteria2://<uuid>:<token>@host:port/…
            // 密码是哈希没法写进 URL，所以单独存一个可逆 token（encrypted cast）
            $table->string('connect_token', 255)->nullable()->after('password');
        });

        // 老用户补一个
        foreach (DB::table('end_users')->select('id')->get() as $row) {
            DB::table('end_users')->where('id', $row->id)->update([
                'connect_token' => Crypt::encryptString(Str::lower(Str::random(32))),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('connect_token');
        });
    }
};
