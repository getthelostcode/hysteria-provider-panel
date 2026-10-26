<?php

namespace Tests\Feature;

use App\Filament\Provider\Widgets\ProviderNodesTable;
use App\Filament\Provider\Widgets\ProviderStatsOverview;
use App\Filament\Provider\Widgets\ProviderTopUsers;
use App\Filament\Provider\Widgets\ProviderTrafficChart;
use App\Models\EndUser;
use App\Models\Node;
use App\Models\NodeUserTraffic;
use App\Models\Provider;
use App\Models\ProviderUser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 ** 3;

    private function seedData(): array
    {
        $provider = Provider::factory()->create([
            'code' => 'alpha01',
            'name' => '星云网络',
            'default_quota_bytes' => 10 * self::GB,
            'default_quota_reset_days' => 7,
            'status' => Provider::STATUS_ACTIVE,
        ]);
        $owner = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        $node = Node::factory()->create([
            'provider_id' => $provider->id,
            'ip' => '203.0.113.50',
            'hostname' => 'jp1.example.com',
            'status' => Node::STATUS_ACTIVE,
            'last_seen_at' => now(),
        ]);

        $user = EndUser::query()->create([
            'provider_id' => $provider->id,
            'email' => 'top@example.com',
            'password' => 'secret-123',
            'status' => EndUser::STATUS_ACTIVE,
            'traffic_quota_bytes' => 10 * self::GB,
            'quota_used_bytes' => 9 * self::GB,
            'quota_reset_days' => 7,
            'quota_reset_at' => now()->addDays(3),
        ]);

        NodeUserTraffic::query()->create([
            'node_id' => $node->id,
            'user_id' => $user->uuid,
            'day' => now()->utc()->toDateString(),
            'tx_bytes' => self::GB,
            'rx_bytes' => (int) (0.5 * self::GB),
        ]);

        return [$provider, $owner, $node, $user];
    }

    private function asProvider(Provider $provider, ProviderUser $owner): void
    {
        $this->actingAs($owner, 'provider');
        Filament::setCurrentPanel(Filament::getPanel('provider'));
        Filament::setTenant($provider);
    }

    public function test_dashboard_renders_all_sections(): void
    {
        [$provider, $owner] = $this->seedData();

        $this->actingAs($owner, 'provider')
            ->get("/provider/{$provider->code}")
            ->assertOk()
            ->assertSee('仪表板')
            ->assertSee('节点在线')
            ->assertSee('今日流量')
            ->assertSee('流量趋势')
            ->assertSee('节点用量')
            ->assertSee('用量最高的用户')
            ->assertSee('203.0.113.50')
            ->assertSee('top@example.com');
    }

    public function test_stats_show_real_numbers(): void
    {
        [$provider, $owner] = $this->seedData();
        $this->asProvider($provider, $owner);

        Livewire::test(ProviderStatsOverview::class)
            ->assertSee('1 / 1')     // 在线 / 全部
            ->assertSee('1.5 GB')    // 今日流量
            ->assertSee('超额用户');
    }

    public function test_chart_defaults_to_14_days(): void
    {
        [$provider, $owner] = $this->seedData();
        $this->asProvider($provider, $owner);

        Livewire::test(ProviderTrafficChart::class)
            ->assertSet('filter', '14')
            ->assertOk();
    }

    public function test_node_and_user_tables_show_records(): void
    {
        [$provider, $owner, $node, $user] = $this->seedData();
        $this->asProvider($provider, $owner);

        Livewire::test(ProviderNodesTable::class)
            ->assertCanSeeTableRecords([$node])
            ->assertSee('203.0.113.50');

        Livewire::test(ProviderTopUsers::class)
            ->assertCanSeeTableRecords([$user])
            ->assertSee('top@example.com');
    }

    public function test_empty_dashboard_has_actionable_empty_states(): void
    {
        $provider = Provider::factory()->create(['code' => 'empty01', 'name' => '空服务商', 'status' => Provider::STATUS_ACTIVE]);
        $owner = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        $this->actingAs($owner, 'provider')
            ->get("/provider/{$provider->code}")
            ->assertOk()
            ->assertSee('还没有接入节点')
            ->assertSee('还没有用户');
    }
}
