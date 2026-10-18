<?php

namespace Tests\Feature;

use App\Models\EndUser;
use App\Models\Node;
use App\Models\NodeUserOnline;
use App\Models\NodeUserTraffic;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JoinRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $code = 'alpha01'): Provider
    {
        return Provider::factory()->create([
            'code' => $code,
            'name' => '运营商 ' . $code,
            'status' => Provider::STATUS_ACTIVE,
        ]);
    }

    public function test_join_page_shows_provider(): void
    {
        $this->provider('alpha01');

        $this->get('/join/alpha01')
            ->assertOk()
            ->assertSee('运营商 alpha01')
            ->assertSee('alpha01'); // 混淆密码
    }

    public function test_join_page_404_for_unknown_or_suspended_provider(): void
    {
        $this->get('/join/nope')->assertNotFound();

        Provider::factory()->suspended()->create(['code' => 'beta02', 'name' => '停用的']);
        $this->get('/join/beta02')->assertNotFound();
    }

    public function test_registration_creates_user_for_provider(): void
    {
        $provider = $this->provider('alpha01');

        $response = $this->post('/join/alpha01', [
            'name' => '张三',
            'email' => 'Wang@Example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect('/join/alpha01/welcome');

        $user = EndUser::query()->sole();
        $this->assertSame($provider->id, $user->provider_id);
        $this->assertSame('wang@example.com', $user->email);       // 统一小写
        $this->assertSame(32, strlen($user->uuid));                 // Hysteria user id
        $this->assertSame(EndUser::STATUS_ACTIVE, $user->status);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_welcome_page_shows_client_config(): void
    {
        $provider = $this->provider('alpha01');
        Node::factory()->create([
            'provider_id' => $provider->id,
            'hostname' => 'jp1.example.com',
            'last_seen_at' => now(),
        ]);

        $this->post('/join/alpha01', [
            'email' => 'joe@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/join/alpha01/welcome');

        $user = EndUser::query()->sole();

        $this->get('/join/alpha01/welcome')
            ->assertOk()
            ->assertSee($user->uuid)
            ->assertSee('joe@example.com')
            ->assertSee('server: jp1.example.com:443')
            ->assertSee('salamander')      // 客户端配置里的 obfs
            ->assertSee('password: alpha01');
    }

    public function test_duplicate_email_rejected_across_providers(): void
    {
        $this->provider('alpha01');
        $this->provider('beta02');

        EndUser::query()->create([
            'provider_id' => Provider::query()->where('code', 'alpha01')->value('id'),
            'email' => 'dup@example.com',
            'password' => 'secret123',
            'status' => EndUser::STATUS_ACTIVE,
        ]);

        $this->from('/join/beta02')
            ->post('/join/beta02', [
                'email' => 'dup@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, EndUser::query()->count());
    }

    public function test_welcome_lists_provider_servers_with_status_and_counts(): void
    {
        $provider = $this->provider('alpha01');
        $other = $this->provider('beta02');

        $online = Node::factory()->create([
            'provider_id' => $provider->id,
            'name' => '东京-01',
            'hostname' => 'jp1.example.com',
            'last_seen_at' => now(),
            'version' => 'v2.13.0',
        ]);
        $offline = Node::factory()->offline()->create([
            'provider_id' => $provider->id,
            'name' => '大阪-02',
            'hostname' => 'os2.example.com',
            'version' => 'v2.12.3',
        ]);
        Node::factory()->disabled()->create([
            'provider_id' => $provider->id,
            'name' => '停用的节点',
            'hostname' => 'off.example.com',
        ]);
        Node::factory()->create([
            'provider_id' => $other->id,
            'name' => '别家的节点',
            'hostname' => 'other.example.com',
        ]);

        $this->post('/join/alpha01', [
            'email' => 'joe@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/join/alpha01/welcome');

        $user = EndUser::query()->sole();
        $user->update(['traffic_quota_bytes' => 10 * 1024 ** 3, 'expires_at' => now()->addDays(30)]);

        // 该运营商两台在线用户快照：在线节点 2 人 3 连接
        NodeUserOnline::query()->create(['node_id' => $online->id, 'user_id' => 'u1', 'connections' => 2, 'seen_at' => now()]);
        NodeUserOnline::query()->create(['node_id' => $online->id, 'user_id' => 'u2', 'connections' => 1, 'seen_at' => now()]);
        NodeUserOnline::query()->create(['node_id' => $offline->id, 'user_id' => 'u3', 'connections' => 4, 'seen_at' => now()->subHour()]);

        // 我自己在这台在线节点上的流量
        NodeUserTraffic::query()->create([
            'node_id' => $online->id, 'user_id' => $user->uuid, 'day' => now()->toDateString(),
            'tx_bytes' => 1024 ** 2, 'rx_bytes' => 3 * 1024 ** 2,
        ]);

        $response = $this->get('/join/alpha01/welcome')->assertOk();

        $response->assertSee('可用服务器');
        $response->assertSee('东京-01');
        $response->assertSee('jp1.example.com');
        $response->assertSee('大阪-02');
        $response->assertSee('在线');
        $response->assertSee('离线');
        $response->assertSee('v2.13.0');
        $response->assertSee('10.00 GB');        // 配额
        $response->assertSee('4.00 MB');         // 我的已用流量
        $response->assertSee($user->uuid);

        // 停用节点/别家节点不出现
        $response->assertDontSee('停用的节点');
        $response->assertDontSee('别家的节点');
        $response->assertDontSee('other.example.com');

        // 客户端配置按节点生成，混淆密码是本运营商标识
        $response->assertSee('server: jp1.example.com:443');
        $response->assertSee('server: os2.example.com:443');
        $response->assertSee('password: alpha01');
    }

    public function test_password_rules(): void
    {
        $this->provider('alpha01');

        $this->post('/join/alpha01', [
            'email' => 'x@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post('/join/alpha01', [
            'email' => 'x@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'different1',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, EndUser::query()->count());
    }

    public function test_join_link_is_provider_specific(): void
    {
        $a = $this->provider('alpha01');
        $b = $this->provider('beta02');

        $this->assertStringEndsWith('/join/alpha01', $a->joinUrl());
        $this->assertStringEndsWith('/join/beta02', $b->joinUrl());
    }
}
