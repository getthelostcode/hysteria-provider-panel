<?php

namespace Tests\Feature;

use App\Filament\Provider\Pages\ConfigTemplatePage;
use App\Models\ConfigTemplate;
use App\Models\Provider;
use App\Models\ProviderConfigTemplate;
use App\Models\ProviderUser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderConfigTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $code = 'alpha01', string $name = '服务商 A'): Provider
    {
        return Provider::factory()->create(['code' => $code, 'name' => $name, 'status' => Provider::STATUS_ACTIVE]);
    }

    public function test_page_shows_standard_template_and_install_command(): void
    {
        $provider = $this->provider('alpha01');
        $user = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        $this->actingAs($user, 'provider')
            ->get('/provider/alpha01/config-template')
            ->assertOk()
            ->assertSee('平台标准模板')
            ->assertSee('一键安装本运营商的节点')
            ->assertSee('curl -fsSL')
            ->assertSee('| sudo bash -s -- --code alpha01')
            // 一键安装在页面中间：在配置信息之后、模板编辑器之前
            ->assertSeeInOrder(['当前生效', '一键安装本运营商的节点', '自定义 config.yaml'])
            ->assertSee('v' . ConfigTemplate::standard()->version);
    }

    public function test_provider_can_save_custom_template(): void
    {
        $provider = $this->provider('alpha01');
        $user = ProviderUser::factory()->create(['provider_id' => $provider->id, 'email' => 'ops@alpha.test']);

        Filament::setCurrentPanel(Filament::getPanel('provider'));
        Filament::setTenant($provider, isQuiet: true);

        $content = "listen: ':8443'\nbandwidth:\n  up: '500 mbps'\n  down: '500 mbps'\n";

        Livewire::actingAs($user, 'provider')
            ->test(ConfigTemplatePage::class)
            ->fillForm(['content' => $content])
            ->call('save')
            ->assertHasNoFormErrors();

        $own = ProviderConfigTemplate::query()->sole();
        $this->assertSame($provider->id, $own->provider_id);
        $this->assertSame(1, $own->version);
        $this->assertSame('ops@alpha.test', $own->updated_by);
        $this->assertSame($content, $own->content);

        // 接口按 code 返回自定义模板
        $this->getJson('/api/config-template?code=alpha01')
            ->assertOk()
            ->assertJsonPath('source', 'provider')
            ->assertJsonPath('version', 1)
            ->assertJsonPath('content', $content);

        // 再存一次版本递增
        Livewire::actingAs($user, 'provider')
            ->test(ConfigTemplatePage::class)
            ->fillForm(['content' => $content . "# v2\n"])
            ->call('save');

        $this->assertSame(2, $own->fresh()->version);
    }

    public function test_clearing_content_falls_back_to_standard(): void
    {
        $provider = $this->provider('alpha01');
        $user = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        ProviderConfigTemplate::query()->create([
            'provider_id' => $provider->id,
            'content' => "listen: ':8443'\n",
            'version' => 3,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('provider'));
        Filament::setTenant($provider, isQuiet: true);

        Livewire::actingAs($user, 'provider')
            ->test(ConfigTemplatePage::class)
            ->fillForm(['content' => '   '])
            ->call('save');

        $this->assertSame(0, ProviderConfigTemplate::query()->count());

        $this->getJson('/api/config-template?code=alpha01')
            ->assertOk()
            ->assertJsonPath('source', 'standard')
            ->assertJsonPath('version', ConfigTemplate::standard()->version);
    }

    public function test_override_is_scoped_to_provider(): void
    {
        $a = $this->provider('alpha01');
        $b = $this->provider('beta02', '服务商 B');

        ProviderConfigTemplate::query()->create([
            'provider_id' => $a->id,
            'content' => "listen: ':8443'\n",
            'version' => 1,
        ]);

        $this->getJson('/api/config-template?code=alpha01')->assertJsonPath('source', 'provider');

        // B 没有自定义 → 平台标准
        $this->getJson('/api/config-template?code=beta02')
            ->assertJsonPath('source', 'standard')
            ->assertJsonPath('version', ConfigTemplate::standard()->version);

        // 未知 code 也回退标准，不报错
        $this->getJson('/api/config-template?code=nope')->assertOk()->assertJsonPath('source', 'standard');
    }

    public function test_use_standard_button_only_clears_form(): void
    {
        $provider = $this->provider('alpha01');
        $user = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        $own = ProviderConfigTemplate::query()->create([
            'provider_id' => $provider->id,
            'content' => "listen: ':8443'\n",
            'version' => 2,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('provider'));
        Filament::setTenant($provider, isQuiet: true);

        Livewire::actingAs($user, 'provider')
            ->test(ConfigTemplatePage::class)
            ->assertSet('data.content', "listen: ':8443'\n")
            ->call('useStandard')
            ->assertSet('data.content', '');

        // 没保存，库里仍是自定义
        $this->assertSame(2, $own->fresh()->version);
    }
}
