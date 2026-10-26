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

    public function test_sitemap_lists_public_pages(): void
    {
        $path = public_path('site/sitemap.xml');
        $this->assertFileExists($path);

        $xml = simplexml_load_string((string) file_get_contents($path));
        $this->assertNotFalse($xml, 'sitemap.xml 不是合法 XML');

        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = (string) $url->loc;
        }

        $this->assertContains('https://hysteria.ipx.sarl/site/', $locs);
        $this->assertSame('2026-10-26', (string) $xml->url[0]->lastmod);
        $this->assertContains('https://hysteria.ipx.sarl/site/free.html', $locs);
        $this->assertContains('https://hysteria.ipx.sarl/provider-signup', $locs);
        $this->assertNotContains('https://hysteria.ipx.sarl/admin', $locs);
        $this->assertNotContains('https://hysteria.ipx.sarl/user', $locs);
    }

    public function test_robots_points_to_sitemap_and_hides_backend(): void
    {
        $robots = (string) file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://hysteria.ipx.sarl/site/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /user', $robots);
        $this->assertStringContainsString('Disallow: /api/', $robots);
    }

    public function test_pages_have_canonical_urls(): void
    {
        $this->assertStringContainsString(
            '<link rel="canonical" href="https://hysteria.ipx.sarl/site/">',
            (string) file_get_contents(public_path('site/index.html')),
        );
        $this->assertStringContainsString(
            '<link rel="canonical" href="https://hysteria.ipx.sarl/site/free.html">',
            (string) file_get_contents(public_path('site/free.html')),
        );
    }

    public function test_pages_have_keywords_and_structured_data(): void
    {
        foreach (['site/index.html', 'site/free.html'] as $file) {
            $html = (string) file_get_contents(public_path($file));

            $this->assertMatchesRegularExpression('/<meta name="keywords" content="[^"]{10,}">/', $html, $file);

            preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
            $this->assertNotEmpty($m, $file . ' 缺 JSON-LD');

            $data = json_decode($m[1], true);
            $this->assertIsArray($data, $file . ' JSON-LD 不是合法 JSON');
            $this->assertArrayHasKey('@graph', $data);

            $types = array_column($data['@graph'], '@type');
            $this->assertContains('FAQPage', $types);
        }
    }
}
