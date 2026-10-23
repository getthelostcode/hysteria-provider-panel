<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingSiteTest extends TestCase
{
    public function test_site_entry_redirects_to_index_file(): void
    {
        // 落到真实文件路径，页面里的 assets/... 才相对 /site/ 解析
        $this->get('/site')->assertStatus(301)->assertRedirect('/site/index.html');
    }

    public function test_site_serves_marketing_page(): void
    {
        $this->get('/site/index.html')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('把 Hysteria2 节点', false)
            ->assertSee('assets/styles.css', false);
    }

    public function test_site_files_are_published(): void
    {
        foreach ([
            'site/index.html',
            'site/assets/styles.css',
            'site/assets/app.js',
            'site/assets/favicon.svg',
        ] as $file) {
            $this->assertFileExists(public_path($file));
        }

        $html = (string) file_get_contents(public_path('site/index.html'));
        $this->assertStringContainsString('把 Hysteria2 节点', $html);
        $this->assertStringContainsString('assets/styles.css', $html);
        $this->assertStringContainsString('个人', $html);
        $this->assertStringContainsString('公司', $html);
    }
}
