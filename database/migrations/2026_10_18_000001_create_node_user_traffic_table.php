<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 每个节点上每个用户的流量（按天累计）
        Schema::create('node_user_traffic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->string('user_id', 190);
            $table->string('day', 10);                 // YYYY-MM-DD（UTC）
            $table->unsignedBigInteger('tx_bytes')->default(0);
            $table->unsignedBigInteger('rx_bytes')->default(0);
            $table->timestamps();

            $table->unique(['node_id', 'user_id', 'day']);
            $table->index('day');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_user_traffic');
    }
};
