<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 服务商名称全局唯一。
 *
 * 先给历史重复数据加后缀（#id），再加唯一索引——否则直接加索引会失败。
 */
return new class extends Migration
{
    public function up(): void
    {
        $seen = [];

        foreach (DB::table('providers')->orderBy('id')->get(['id', 'name']) as $row) {
            $name = trim((string) $row->name);
            $key = mb_strtolower($name);

            if ($name !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;

                continue;
            }

            $suffix = ' #' . $row->id;
            $new = mb_substr($name !== '' ? $name : '服务商', 0, 120 - mb_strlen($suffix)) . $suffix;

            DB::table('providers')->where('id', $row->id)->update(['name' => $new]);
            $seen[mb_strtolower($new)] = true;
        }

        Schema::table('providers', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
