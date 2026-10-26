<?php

namespace Tests\Feature;

use App\Filament\Resources\ProviderResource\Pages\EditProvider;
use App\Models\Admin;
use App\Models\Node;
use App\Models\Provider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class PublicProviderListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function provider(string $code, bool $listed, string $status = Provider::STATUS_ACTIVE): Provider
    {
        return Provider::factory()->create([
            'code' => $code,
            'name' => '服务商 ' . $code,
            'status' => $status,
            'listed' => $listed,
        ]);
    }

    public function test_lists_only_active_and_listed_providers(): void
    {
        $listed = $this->provider('listed01', true);
        $unlisted = $this->provider('hidden01', false);
        $suspended = $this->provider('susp01', true, Provider::STATUS_SUSPENDED);

        Node::factory()->count(3)->create([
            'provider_id' => $listed->id,
            'status' => Node::STATUS_ACTIVE,
            'last_seen_at' => now(),
        ]);

        $response = $this->getJson('/api/public/providers')->assertOk();

        $codes = collect($response->json('providers'))->pluck('code')->all();
        $this->assertContains('listed01', $codes);
        $this->assertNotContains('hidden01', $codes);
        $this->assertNotContains('susp01', $codes);
        $this->assertSame(1, $response->json('count'));

        $row = $response->json('providers.0');
        $this->assertSame(3, $row['nodes']);
        $this->assertSame(3, $row['online']);
        $this->assertStringEndsWith('/join/listed01', $row['join_url']);
        $this->assertStringContainsString('free.html?code=listed01', $row['free_url']);
    }

    public function test_offline_nodes_are_not_counted_as_online(): void
    {
        $provider = $this->provider('listed02', true);

        Node::factory()->create([
            'provider_id' => $provider->id,
            'status' => Node::STATUS_ACTIVE,
            'last_seen_at' => now(),
        ]);
        Node::factory()->create([
            'provider_id' => $provider->id,
            'status' => Node::STATUS_ACTIVE,
            'last_seen_at' => now()->subHour(),
        ]);

        $row = $this->getJson('/api/public/providers')->assertOk()->json('providers.0');

        $this->assertSame(2, $row['nodes']);
        $this->assertSame(1, $row['online']);
    }

    public function test_admin_can_toggle_listing(): void
    {
        $provider = $this->provider('toggle01', false);
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditProvider::class, ['record' => $provider->getRouteKey()])
            ->fillForm([
                'code' => $provider->code,
                'name' => $provider->name,
                'status' => Provider::STATUS_ACTIVE,
                'listed' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($provider->refresh()->listed);

        Cache::flush();
        $this->getJson('/api/public/providers')
            ->assertOk()
            ->assertJsonPath('providers.0.code', 'toggle01');
    }

    public function test_site_page_contains_provider_list_section(): void
    {
        $html = (string) file_get_contents(public_path('site/index.html'));

        $this->assertStringContainsString('服务商列表', $html);
        $this->assertStringContainsString('id="providerList"', $html);
        $this->assertStringContainsString('/api/public/providers', $html);
    }

    public function test_listing_changes_are_visible_immediately(): void
    {
        $provider = $this->provider('instant01', false);

        $this->getJson('/api/public/providers')->assertOk()->assertJsonPath('count', 0);

        $provider->update(['listed' => true]);
        $this->getJson('/api/public/providers')->assertOk()->assertJsonPath('providers.0.code', 'instant01');

        $provider->update(['listed' => false]);
        $this->getJson('/api/public/providers')->assertOk()->assertJsonPath('count', 0);
    }
}
