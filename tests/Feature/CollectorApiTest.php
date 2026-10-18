<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\NodeKickQueue;
use App\Models\NodeUserOnline;
use App\Models\NodeUserTraffic;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectorApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'collector-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['collector.token' => self::TOKEN]);
    }

    private function headers(): array
    {
        return ['X-Collector-Token' => self::TOKEN];
    }

    public function test_token_required(): void
    {
        $this->getJson('/api/collector/v1/nodes')->assertStatus(401);
        $this->getJson('/api/collector/v1/nodes', ['X-Collector-Token' => 'wrong'])->assertStatus(401);
        $this->getJson('/api/collector/v1/nodes', $this->headers())->assertOk();
    }

    public function test_disabled_when_token_not_configured(): void
    {
        config(['collector.token' => '']);

        $this->getJson('/api/collector/v1/nodes', $this->headers())->assertStatus(503);
    }

    public function test_nodes_lists_only_active_node_and_provider(): void
    {
        $active = Provider::factory()->create(['code' => 'carrier01']);
        $suspended = Provider::factory()->suspended()->create(['code' => 'carrier02']);

        $node = Node::factory()->create([
            'provider_id' => $active->id,
            'ip' => '127.0.0.1',
            'traffic_port' => 18080,
            'traffic_secret' => 'secret-abc',
            'status' => Node::STATUS_ACTIVE,
        ]);
        Node::factory()->create(['provider_id' => $active->id, 'status' => Node::STATUS_DISABLED]);
        Node::factory()->create(['provider_id' => $suspended->id, 'status' => Node::STATUS_ACTIVE]);

        $response = $this->getJson('/api/collector/v1/nodes', $this->headers())->assertOk();

        $nodes = $response->json('nodes');
        $this->assertCount(1, $nodes);
        $this->assertSame((string) $node->id, $nodes[0]['id']);
        $this->assertSame('http://127.0.0.1:18080', $nodes[0]['api_base']);
        $this->assertSame('secret-abc', $nodes[0]['secret']);
    }

    public function test_ingest_accumulates_and_is_idempotent(): void
    {
        $node = Node::factory()->create(['ip' => '127.0.0.1', 'traffic_port' => 18080]);

        $payload = [
            'node_id' => (string) $node->id,
            'polled_at' => '2026-10-18T01:00:00Z',
            'users' => [
                ['user_id' => 'wang', 'tx' => 100, 'rx' => 200],
                ['user_id' => 'joe', 'tx' => 0, 'rx' => 0],
            ],
            'online' => [['user_id' => 'wang', 'connections' => 2]],
        ];

        $this->postJson('/api/collector/v1/ingest', $payload, $this->headers())->assertOk();

        $row = NodeUserTraffic::query()->where('user_id', 'wang')->sole();
        $this->assertSame(100, $row->tx_bytes);
        $this->assertSame(200, $row->rx_bytes);
        $this->assertSame('2026-10-18', $row->day);

        // joe 全 0 不落库
        $this->assertSame(0, NodeUserTraffic::query()->where('user_id', 'joe')->count());

        // 同一 polled_at 重放：幂等，不重复计
        $this->postJson('/api/collector/v1/ingest', $payload, $this->headers())
            ->assertOk()
            ->assertJsonPath('duplicate', true);
        $this->assertSame(100, $row->fresh()->tx_bytes);

        // 下一批：累加 + 在线整体替换
        $this->postJson('/api/collector/v1/ingest', [
            'node_id' => (string) $node->id,
            'polled_at' => '2026-10-18T01:00:30Z',
            'users' => [['user_id' => 'wang', 'tx' => 50, 'rx' => 25]],
            'online' => [['user_id' => 'joe', 'connections' => 1]],
        ], $this->headers())->assertOk();

        $this->assertSame(150, $row->fresh()->tx_bytes);
        $this->assertSame(225, $row->fresh()->rx_bytes);

        $online = NodeUserOnline::query()->where('node_id', $node->id)->get();
        $this->assertCount(1, $online);
        $this->assertSame('joe', $online[0]->user_id);

        $node->refresh();
        $this->assertNotNull($node->last_seen_at);
        $this->assertNotNull($node->last_ingest_at);
    }

    public function test_ingest_unknown_node(): void
    {
        $this->postJson('/api/collector/v1/ingest', [
            'node_id' => '9999',
            'users' => [],
        ], $this->headers())->assertStatus(404)->assertJsonPath('error', 'unknown_node');
    }

    public function test_kick_queue_and_result(): void
    {
        $first = NodeKickQueue::query()->create([
            'uuid' => 'uuid-1',
            'reason' => 'quota',
            'status' => NodeKickQueue::STATUS_PENDING,
        ]);
        NodeKickQueue::query()->create([
            'uuid' => 'uuid-2',
            'status' => NodeKickQueue::STATUS_PENDING,
        ]);

        $this->getJson('/api/collector/v1/kick-queue', $this->headers())
            ->assertOk()
            ->assertJsonCount(2, 'queue');

        $this->postJson('/api/collector/v1/kick-result', [
            'id' => $first->id,
            'node_id' => '1',
            'http_status' => 200,
            'note' => '{"ok":true}',
        ], $this->headers())->assertOk()->assertJsonPath('status', 'kicked');

        $this->assertSame(NodeKickQueue::STATUS_KICKED, $first->fresh()->status);
        $this->assertNotNull($first->fresh()->processed_at);

        $this->getJson('/api/collector/v1/kick-queue', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'queue');

        // 重复回传 / 未知 id
        $this->postJson('/api/collector/v1/kick-result', [
            'id' => 9999,
            'http_status' => 200,
        ], $this->headers())->assertStatus(404);
    }

    public function test_kick_result_failure_marks_failed(): void
    {
        $entry = NodeKickQueue::query()->create([
            'uuid' => 'uuid-fail',
            'status' => NodeKickQueue::STATUS_PENDING,
        ]);

        $this->postJson('/api/collector/v1/kick-result', [
            'uuid' => 'uuid-fail',
            'http_status' => 500,
            'note' => 'node error',
        ], $this->headers())->assertOk()->assertJsonPath('status', 'failed');

        $this->assertSame(NodeKickQueue::STATUS_FAILED, $entry->fresh()->status);
    }
}
