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
            'cert_sha256' => '7A:FB:CA:F6:EE:06:3A:D5:EC:4F:E1:1F:E8:7B:79:0F:D0:74:92:D5:68:48:42:31:02:9F:FF:3C:3F:20:63:EC',
            'cert_pin_sha256' => 'evvK9u4GOtXsT+Ef6Ht5D9B0ktVoSEIxAp//PD8gY+w=',
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

        // 只有账号（uuid），不带密码
        $this->assertStringStartsWith('hysteria2://' . $user->uuid . '@jp1.example.com:443/?', $uri);
        // sni 固定 bing.com，与节点域名无关
        $this->assertStringContainsString('sni=bing.com', $uri);
        $this->assertStringContainsString('obfs=salamander', $uri);
        $this->assertStringContainsString('obfs-password=alpha01', $uri);
        // pinSHA256 是 hex 大写、不带冒号
        $this->assertStringContainsString('pinSHA256=7AFBCAF6EE063AD5EC4FE11FE87B790FD07492D568484231029FFF3C3F2063EC', $uri);
        $this->assertStringNotContainsString('pinSHA256=7A%3A', $uri);
        $this->assertStringNotContainsString('%3D', $uri);
        $this->assertStringNotContainsString('insecure', $uri);
        $this->assertStringEndsWith('#jp1.example.com', $uri);
    }

    public function test_pin_falls_back_to_hex_converted_from_base64(): void
    {
        [$node, $user] = $this->fixtures(['cert_sha256' => null]);

        $uri = HysteriaUri::for($node, $user, 'alpha01');

        $this->assertStringContainsString('pinSHA256=7AFBCAF6EE063AD5EC4FE11FE87B790FD07492D568484231029FFF3C3F2063EC', $uri);
    }

    public function test_sni_is_always_bing_com(): void
    {
        [$ipNode, $user] = $this->fixtures(['hostname' => null, 'ip' => '104.223.108.120']);

        $domainNode = Node::factory()->create([
            'provider_id' => $ipNode->provider_id,
            'name' => 'jp9',
            'hostname' => 'jp9.example.net',
            'cert_pin_sha256' => null,
            'cert_sha256' => null,
        ]);

        $this->assertStringContainsString('sni=bing.com', HysteriaUri::for($ipNode, $user, 'alpha01'));
        $this->assertStringContainsString('sni=bing.com', HysteriaUri::for($domainNode, $user, 'alpha01'));
        $this->assertStringNotContainsString('sni=jp9.example.net', HysteriaUri::for($domainNode, $user, 'alpha01'));
    }

    public function test_ip_node_uses_configured_sni_and_ip_fragment(): void
    {
        // 用户给的格式：hysteria2://<uuid>@104.223.108.120:443/?sni=bing.com&…&pinSHA256=…#104.223.108.120
        [$node, $user] = $this->fixtures([
            'hostname' => null,
            'ip' => '104.223.108.120',
            'name' => '104.223.108.120',
        ]);

        $uri = HysteriaUri::for($node, $user, '4f13b9d93a8afb6e98283702');

        $this->assertStringStartsWith('hysteria2://' . $user->uuid . '@104.223.108.120:443/?', $uri);
        $this->assertStringContainsString('sni=bing.com', $uri);
        $this->assertStringContainsString('obfs-password=4f13b9d93a8afb6e98283702', $uri);
        $this->assertStringContainsString('pinSHA256=', $uri);
        $this->assertStringEndsWith('#104.223.108.120', $uri);
        $this->assertStringNotContainsString('@' . $user->uuid . ':uuid', $uri);
    }

    public function test_falls_back_to_insecure_without_pin(): void
    {
        [$node, $user] = $this->fixtures(['cert_pin_sha256' => null, 'cert_sha256' => null]);

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
            'cert_sha256' => null,
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
