<?php

namespace App\Support;

/**
 * 字节格式化：去掉多余的 .00（1.50 GB → 1.5 GB，10.00 GB → 10 GB）。
 */
class Bytes
{
    public static function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $value = (float) max(0, $bytes);
        $i = 0;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        $text = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $text . ' ' . $units[$i];
    }

    /** 上/下行描述，如 ↑ 1.2 GB ↓ 3.4 GB */
    public static function upDown(int $tx, int $rx): string
    {
        return '↑ ' . self::human($tx) . '　↓ ' . self::human($rx);
    }
}
