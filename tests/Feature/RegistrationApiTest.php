<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'ip' => '203.0.113.10',
            'obfs_password' => 'carrier01',
            'traffic_port' => 12345,
            'traffic_secret' => str_repeat('a', 48),
            'hostname' => 'node1.example.com',
            'version' => 'v2.13.0',
        ], $overrides);
    }

    public function test_registers_node_under_matching_provider(): void
    {
        $provider = Provider::factory()->create(['code' => 'carrier01']);

        $response = $this->postJson('/new', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('provider', 'carrier01');

        $node = Node::query()->sole();
        $this->assertSame($provider->id, $node->provider_id);
        $this->assertSame('203.0.113.10', $node->ip);
        $this->assertSame(12345, (int) $node->traffic_port);
        $this->assertSame(str_repeat('a', 48), $node->traffic_secret);
        $this->assertNotNull($node->last_seen_at);
        $this->assertNotEmpty($response->json('node_token'));

        // 响应里不回显 traffic secret
        $this->assertStringNotContainsString(str_repeat('a', 48), $response->getContent());

        // 库里是密文（encrypted cast）
        $raw = DB::table('nodes')->where('id', $node->id)->value('traffic_secret');
        $this->assertNotSame(str_repeat('a', 48), $raw);
    }

    public function test_stores_certificate_fingerprint(): void
    {
        Provider::factory()->create(['code' => 'carrier01']);

        $hex = implode(':', str_split(str_repeat('ab', 32), 2));
        $pin = base64_encode(random_bytes(32));

        $this->postJson('/new', $this->payload([
            'cert_sha256' => strtoupper($hex),
            'cert_pin_sha256' => $pin,
        ]))->assertCreated();

        $node = Node::query()->sole();
        $this->assertSame(strtoupper($hex), $node->cert_sha256);
        $this->assertSame($pin, $node->cert_pin_sha256);

        // 客户端配置改用 pinSHA256，不再 insecure
        $yaml = $node->clientConfigYaml();
        $this->assertStringContainsString('insecure: false', $yaml);
        $this->assertStringContainsString('pinSHA256: ' . $pin, $yaml);
    }

    public function test_fingerprint_without_pin_still_allowed_but_keeps_insecure(): void
    {
        Provider::factory()->create(['code' => 'carrier01']);

        // 老版本 install.sh 只报 ip/端口/secret，完全不带指纹也要能注册
        $this->postJson('/new', $this->payload())->assertCreated();

        $node = Node::query()->sole();
        $this->assertNull($node->cert_sha256);
        $this->assertStringContainsString('insecure: true', $node->clientConfigYaml());
    }

    public function test_rejects_malformed_fingerprint(): void
    {
        Provider::factory()->create(['code' => 'carrier01']);

        $this->postJson('/new', $this->payload(['cert_sha256' => 'not-a-fingerprint']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cert_sha256');
    }

    public function test_registers_without_hostname_uses_ip_as_name(): void
    {
        // register-hysteria/install.sh 实际只发这四个字段
        Provider::factory()->create(['code' => 'carrier01']);

        $response = $this->postJson('/new', [
            'ip' => '203.0.113.44',
            'obfs_password' => 'carrier01',
            'traffic_port' => 16666,
            'traffic_secret' => str_repeat('c', 48),
        ]);

        $response->assertCreated();
        $this->assertSame('203.0.113.44', Node::query()->sole()->name);
        $this->assertNull(Node::query()->sole()->hostname);
    }

    public function test_validation_error_is_json_even_without_accept_header(): void
    {
        // register-hysteria 的 curl 只带 Content-Type，不带 Accept；接口必须回 JSON 而不是 302+HTML
        $response = $this->post('/new', ['ip' => 'not-an-ip'], ['Content-Type' => 'application/json']);

        $response->assertStatus(422);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function test_registration_is_idempotent_by_ip(): void
    {
        Provider::factory()->create(['code' => 'carrier01']);

        $first = $this->postJson('/new', $this->payload());
        $second = $this->postJson('/new', $this->payload([
            'traffic_port' => 13333,
            'traffic_secret' => str_repeat('b', 32),
        ]));

        $first->assertCreated();
        $second->assertOk();

        $this->assertSame(1, Node::query()->count());
        $this->assertSame(13333, (int) Node::query()->sole()->traffic_port);
        $this->assertNotSame($first->json('node_token'), $second->json('node_token'));
    }

    public function test_unknown_code_is_rejected(): void
    {
        $this->postJson('/new', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('error', 'unknown_code');

        $this->assertSame(0, Node::query()->count());
    }

    public function test_suspended_provider_cannot_register(): void
    {
        Provider::factory()->suspended()->create(['code' => 'carrier01']);

        $this->postJson('/new', $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('error', 'provider_suspended');
    }

    public function test_payload_is_validated(): void
    {
        $this->postJson('/new', ['ip' => 'not-an-ip'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ip', 'obfs_password', 'traffic_port', 'traffic_secret']);
    }

    public function test_shared_secret_header_can_be_required(): void
    {
        config(['hysteria.register_shared_secret' => 's3cr3t']);
        Provider::factory()->create(['code' => 'carrier01']);

        $this->postJson('/new', $this->payload())->assertStatus(401);

        $this->postJson('/new', $this->payload(), ['X-Register-Secret' => 's3cr3t'])
            ->assertCreated();
    }
}
