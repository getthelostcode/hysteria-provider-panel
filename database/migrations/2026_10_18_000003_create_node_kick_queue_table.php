<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 待踢队列：面板里加 uuid，采集器下一轮发现它在线就踢
        Schema::create('node_kick_queue', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 190);
            $table->string('reason', 190)->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending|kicked|failed
            $table->string('requested_by', 190)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('node_id', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['uuid', 'status']);
        });

        // 采集器上报的批次时间：用来做幂等，避免重试把同一批流量算两遍
        Schema::table('nodes', function (Blueprint $table) {
            $table->timestamp('last_ingest_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('last_ingest_at');
        });

        Schema::dropIfExists('node_kick_queue');
    }
};
