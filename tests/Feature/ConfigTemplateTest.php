<?php

namespace Tests\Feature;

use App\Filament\Pages\ConfigTemplateSettings;
use App\Models\Admin;
use App\Models\ConfigTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfigTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_standard_template_with_hash(): void
    {
        $response = $this->getJson('/api/config-template')->assertOk();

        $response->assertJsonPath('name', 'standard')
            ->assertJsonPath('version', 1);

        $content = $response->json('content');
        $this->assertStringContainsString("listen: ':443'", $content);
        $this->assertStringContainsString('trafficStats:', $content);
        $this->assertSame(hash('sha256', $content), $response->json('sha256'));

        // 库里就一条 standard
        $this->assertSame(1, ConfigTemplate::query()->count());
    }

    public function test_admin_page_requires_login(): void
    {
        $this->get('/admin/config-template')->assertRedirect('/admin/login');
    }

    public function test_admin_can_edit_and_save_template(): void
    {
        $admin = Admin::factory()->create(['email' => 'ops@example.com']);

        $this->actingAs($admin, 'admin')->get('/admin/config-template')
            ->assertOk()
            ->assertSee('Hysteria 配置模板')
            ->assertSee('当前版本')
            ->assertSee('v1');

        $newContent = str_replace("bandwidth:\n  up: '1 gbps'\n  down: '1 gbps'", "bandwidth:\n  up: '2 gbps'\n  down: '2 gbps'", ConfigTemplate::defaultContent());

        Livewire::actingAs($admin, 'admin')
            ->test(ConfigTemplateSettings::class)
            ->fillForm(['content' => $newContent])
            ->call('save')
            ->assertHasNoFormErrors();

        $template = ConfigTemplate::standard();
        $this->assertSame(2, $template->version);
        $this->assertSame($newContent, $template->content);
        $this->assertSame('ops@example.com', $template->updated_by);

        // 接口立即反映新版本与新 hash
        $this->getJson('/api/config-template')
            ->assertJsonPath('version', 2)
            ->assertJsonPath('sha256', hash('sha256', $newContent))
            ->assertJsonPath('updated_at', fn ($v) => $v !== null);
    }

    public function test_reset_to_default_only_fills_form(): void
    {
        $admin = Admin::factory()->create();
        $template = ConfigTemplate::standard();
        $template->update(['content' => "listen: ':8443'\n", 'version' => 5]);

        Livewire::actingAs($admin, 'admin')
            ->test(ConfigTemplateSettings::class)
            ->assertSet('data.content', "listen: ':8443'\n")
            ->call('resetToDefault')
            ->assertSet('data.content', ConfigTemplate::defaultContent());

        // 没点保存，库里不变
        $this->assertSame(5, $template->fresh()->version);
        $this->assertSame("listen: ':8443'\n", $template->fresh()->content);
    }

    public function test_save_validates_empty_content(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(ConfigTemplateSettings::class)
            ->fillForm(['content' => ''])
            ->call('save')
            ->assertHasFormErrors(['content']);
    }
}
