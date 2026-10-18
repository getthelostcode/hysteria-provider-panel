<?php

namespace App\Support;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    /** 生成 SVG 二维码字符串（不落地文件，直接嵌页面） */
    public static function svg(string $data, int $size = 240, int $margin = 8): string
    {
        $writer = new SvgWriter();
        $result = $writer->write(new QrCode(data: $data, size: $size, margin: $margin));

        return $result->getString();
    }
}
