<?php

namespace Tests\Feature;

use App\Models\EndUser;
use App\Models\Node;
use App\Models\Provider;
use App\Support\HysteriaUri;
use App\Support\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HysteriaUriTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(array $nodeAttrs = []): array
    {
        $provider = Provider::factory()->create(['code' => 'alpha01']);
        $node = Node::factory()->create(array_merge([
            'provider_id' => $provider->id,
            'name' => '东京-01',
            'hostname' => 'jp1.example.com',
            'ip' => '203.0.113.9',
            'cert_pin_sha256' => 'VNqBZf+7gTlNdyIHs22KOLfTKC3TcelKDugIIo63h2M=',
        ], $nodeAttrs));

        $user = EndUser::query()->create([
            'provider_id' => $provider->id,
            'email' => 'joe@example.com',
            'password' => 'secret123',
            'connect_token' => 'tokentoken123',
            'status' => EndUser::STATUS_ACTIVE,
        ]);

        return [$node, $user];
    }

    public function test_builds_hysteria2_uri_with_pin(): void
    {
        [$node, $user] = $this->fixtures();

        $uri = HysteriaUri::for($node, $user, 'alpha01');

        // 客户端参数里账号与密码都是 uuid
        $this->assertStringStartsWith('hysteria2://' . $user->uuid . ':' . $user->uuid . '@jp1.example.com:443/?', $uri);
        $this->assertStringContainsString('sni=jp1.example.com', $uri);
        $this->assertStringContainsString('obfs=salamander', $uri);
        $this->assertStringContainsString('obfs-password=alpha01', $uri);
        $this->assertStringContainsString('pinSHA256=' . rawurlencode('VNqBZf+7gTlNdyIHs22KOLfTKC3TcelKDugIIo63h2M='), $uri);
        $this->assertStringNotContainsString('insecure', $uri);
        $this->assertStringEndsWith('#' . rawurlencode('东京-01'), $uri);
    }

    public function test_falls_back_to_insecure_without_pin(): void
    {
        [$node, $user] = $this->fixtures(['cert_pin_sha256' => null]);

        $uri = HysteriaUri::for($node, $user, 'alpha01');

        $this->assertStringContainsString('insecure=1', $uri);
        $this->assertStringNotContainsString('pinSHA256', $uri);
    }

    public function test_uses_ip_when_no_hostname_and_brackets_ipv6(): void
    {
        [$node, $user] = $this->fixtures(['hostname' => null]);

        $this->assertStringContainsString('@203.0.113.9:443/', HysteriaUri::for($node, $user, 'alpha01'));

        $v6 = Node::factory()->create([
            'provider_id' => $node->provider_id,
            'name' => 'v6',
            'hostname' => null,
            'ip' => '2001:db8::1',
            'cert_pin_sha256' => null,
        ]);
        $this->assertStringContainsString('@[2001:db8::1]:443/', HysteriaUri::for($v6, $user, 'alpha01'));
    }

    public function test_connect_token_is_hidden_from_serialization(): void
    {
        [, $user] = $this->fixtures();

        $this->assertArrayNotHasKey('connect_token', $user->toArray());
        $this->assertSame('tokentoken123', $user->connect_token);
    }

    public function test_qr_svg_contains_svg_markup(): void
    {
        $svg = QrCodeService::svg('hysteria2://abc:def@example.com:443/?sni=example.com');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }
}
