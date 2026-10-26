<?php

namespace Tests\Feature;

use App\Models\EndUser;
use App\Models\Node;
use App\Models\NodeUserTraffic;
use App\Models\Provider;
use App\Models\ProviderUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 服务商后台能看到：
 * - 用户列表：本期用量 + 累计用量（不限流量也要显示用了多少）
 * - 节点列表：IP + 今日流量 + 累计流量
 */
class ProviderUsageVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 ** 3;

    private function seedData(): array
    {
        $provider = Provider::factory()->create(['code' => 'alpha01', 'status' => Provider::STATUS_ACTIVE]);
        $owner = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        $node = Node::factory()->create([
            'provider_id' => $provider->id,
            'ip' => '203.0.113.9',
            'hostname' => 'jp1.example.com',
            'status' => Node::STATUS_ACTIVE,
        ]);

        $user = EndUser::query()->create([
            'provider_id' => $provider->id,
            'email' => 'usage@example.com',
            'password' => 'secret-123',
            'status' => EndUser::STATUS_ACTIVE,
            // 不设配额：仍要显示已用流量
            'quota_used_bytes' => (int) (0.25 * self::GB),
        ]);

        $today = now()->utc()->toDateString();
        $yesterday = now()->utc()->subDay()->toDateString();

        $rows = [
            [$today, self::GB, (int) (0.5 * self::GB)],
            [$yesterday, (int) (0.5 * self::GB), (int) (0.5 * self::GB)],
        ];

        foreach ($rows as [$day, $tx, $rx]) {
            NodeUserTraffic::query()->create([
                'node_id' => $node->id,
                'user_id' => $user->uuid,
                'day' => $day,
                'tx_bytes' => $tx,
                'rx_bytes' => $rx,
            ]);
        }

        return [$provider, $owner, $node, $user];
    }

    public function test_users_page_shows_per_user_usage(): void
    {
        [$provider, $owner] = $this->seedData();

        $this->actingAs($owner, 'provider')
            ->get("/provider/{$provider->code}/users")
            ->assertOk()
            ->assertSee('本期用量')
            ->assertSee('累计用量')
            // 不限流量时也显示已用（0.25 GB = 256 MB）
            ->assertSee('256 MB')
            // 累计 = 1.5 GB tx + 1 GB rx
            ->assertSee('2.5 GB')
            ->assertSee('↑ 1.5 GB')
            ->assertSee('↓ 1 GB');
    }

    public function test_nodes_page_shows_ip_and_traffic(): void
    {
        [$provider, $owner, $node] = $this->seedData();

        $this->actingAs($owner, 'provider')
            ->get("/provider/{$provider->code}/nodes")
            ->assertOk()
            ->assertSee('203.0.113.9')
            ->assertSee('jp1.example.com')
            ->assertSee('今日流量')
            ->assertSee('累计流量')
            // 今日 = 1 GB + 0.5 GB
            ->assertSee('1.5 GB')
            // 累计 = 1.5 GB tx + 1 GB rx
            ->assertSee('2.5 GB');
    }
}
