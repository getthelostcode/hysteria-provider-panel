<?php

namespace App\Support;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Log;
use Throwable;

class QrCodeService
{
    /**
     * 生成 SVG 二维码；失败返回 null（页面降级为只显示链接，不抛 500）。
     *
     * 常见失败原因：服务器 vendor 里没装 endroid/qr-code（git pull 后没跑 composer install）。
     */
    public static function svg(string $data, int $size = 240, int $margin = 8): ?string
    {
        try {
            if (! class_exists(QrCode::class)) {
                Log::warning('二维码库缺失：跑 composer install（endroid/qr-code）');

                return null;
            }

            $writer = new SvgWriter();
            $result = $writer->write(new QrCode(data: $data, size: $size, margin: $margin));

            return $result->getString();
        } catch (Throwable $e) {
            Log::warning('二维码生成失败: ' . $e->getMessage());

            return null;
        }
    }
}
