<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 每轮采集的在线快照：节点上当前在线的用户与连接数
        Schema::create('node_user_online', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->string('user_id', 190);
            $table->unsignedInteger('connections')->default(0);
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->unique(['node_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_user_online');
    }
};
