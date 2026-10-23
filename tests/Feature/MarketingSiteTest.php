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
            ->assertSee('把服务器接进来', false)
            ->assertSee('assets/styles.css', false);
    }

    public function test_site_files_are_published(): void
    {
        foreach ([
            'site/index.html',
            'site/free.html',
            'site/assets/styles.css',
            'site/assets/app.js',
            'site/assets/free.css',
            'site/assets/free.js',
            'site/assets/favicon.svg',
        ] as $file) {
            $this->assertFileExists(public_path($file));
        }

        $html = (string) file_get_contents(public_path('site/index.html'));
        $this->assertStringContainsString('把服务器接进来', $html);
        $this->assertStringContainsString('assets/styles.css', $html);
        $this->assertStringContainsString('个人', $html);
        $this->assertStringContainsString('服务商', $html);
        $this->assertStringContainsString('provider-signup', $html);
        $this->assertStringContainsString('free.html', $html);
        // 平台不开源：首页不再出现仓库/自托管口径
        $this->assertStringNotContainsString('github.com', $html);
        $this->assertStringNotContainsString('开源', $html);
        $this->assertStringNotContainsString('自托管', $html);
    }

    public function test_free_page_offers_trial_account(): void
    {
        $html = (string) file_get_contents(public_path('site/free.html'));

        $this->assertStringContainsString('10 GB', $html);
        $this->assertStringContainsString('claimForm', $html);
        $this->assertStringContainsString('assets/free.js', $html);
        $this->assertStringContainsString('assets/free.css', $html);
        $this->assertStringNotContainsString('github.com', $html);
    }
}
