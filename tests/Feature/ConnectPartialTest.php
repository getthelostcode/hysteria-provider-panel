<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectPartialTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_qr_and_link(): void
    {
        $html = view('filament.user.partials.connect', [
            'uri' => 'hysteria2://uuid@1.2.3.4:443/?sni=bing.com',
            'qr' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        ])->render();

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('hysteria2://uuid@1.2.3.4:443', $html);
        $this->assertStringContainsString('copy()', $html);
        $this->assertStringContainsString('getElementById(&#039;connect-uri&#039;)', str_replace("\"", "&#039;", $html));
        $this->assertStringNotContainsString("text: '", $html);   // 不再把 URL 塞进属性（会截断）
        $this->assertStringNotContainsString('二维码生成失败', $html);
    }

    public function test_degrades_gracefully_when_qr_missing(): void
    {
        $html = view('filament.user.partials.connect', [
            'uri' => 'hysteria2://uuid@1.2.3.4:443/?sni=bing.com',
            'qr' => null,
        ])->render();

        $this->assertStringContainsString('二维码生成失败', $html);
        $this->assertStringContainsString('composer install --no-dev', $html);
        // 链接仍然可用
        $this->assertStringContainsString('hysteria2://uuid@1.2.3.4:443', $html);
    }

    public function test_panel_check_command_passes(): void
    {
        $this->artisan('panel:check')->assertSuccessful();
    }
}
