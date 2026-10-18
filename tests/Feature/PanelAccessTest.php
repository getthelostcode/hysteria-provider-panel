<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Node;
use App\Models\Provider;
use App\Models\ProviderUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_pages_render(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/provider/login')->assertOk();
    }

    public function test_admin_panel_pages_render(): void
    {
        $admin = Admin::factory()->create();
        $provider = Provider::factory()->create(['code' => 'alpha01', 'name' => '服务商 A']);
        $node = Node::factory()->create(['provider_id' => $provider->id, 'ip' => '203.0.113.9']);

        $this->actingAs($admin, 'admin')->get('/admin')->assertOk()->assertSee('在线节点');
        $this->actingAs($admin, 'admin')->get('/admin/providers')->assertOk()->assertSee('alpha01');
        $this->actingAs($admin, 'admin')->get('/admin/nodes')->assertOk()->assertSee('203.0.113.9');
        $this->actingAs($admin, 'admin')->get("/admin/nodes/{$node->id}")->assertOk()->assertSee('salamander');
    }

    public function test_provider_panel_pages_render(): void
    {
        $provider = Provider::factory()->create(['code' => 'alpha01']);
        $user = ProviderUser::factory()->create(['provider_id' => $provider->id]);
        $node = Node::factory()->create(['provider_id' => $provider->id, 'ip' => '203.0.113.9']);

        $this->actingAs($user, 'provider')
            ->get("/provider/{$provider->code}")
            ->assertOk()
            ->assertSee('我的节点');

        $this->actingAs($user, 'provider')
            ->get("/provider/{$provider->code}/nodes/{$node->id}")
            ->assertOk()
            ->assertSee('salamander');
    }

    public function test_end_user_pages_in_both_panels(): void
    {
        $admin = Admin::factory()->create();
        $provider = Provider::factory()->create(['code' => 'alpha01', 'name' => '服务商 A']);
        $other = Provider::factory()->create(['code' => 'beta02', 'name' => '服务商 B']);

        $mine = \App\Models\EndUser::query()->create([
            'provider_id' => $provider->id, 'email' => 'mine@example.com',
            'password' => 'secret123', 'status' => 'active',
        ]);
        $theirs = \App\Models\EndUser::query()->create([
            'provider_id' => $other->id, 'email' => 'theirs@example.com',
            'password' => 'secret123', 'status' => 'active',
        ]);

        // 平台后台：能看到所有运营商的用户
        $this->actingAs($admin, 'admin')->get('/admin/end-users')
            ->assertOk()
            ->assertSee('mine@example.com')
            ->assertSee('theirs@example.com');

        // 服务商后台：只看自己租户的
        $providerUser = ProviderUser::factory()->create(['provider_id' => $provider->id]);
        $this->actingAs($providerUser, 'provider')
            ->get("/provider/{$provider->code}/users")
            ->assertOk()
            ->assertSee('mine@example.com')
            ->assertDontSee('theirs@example.com');

        $this->actingAs($providerUser, 'provider')
            ->get("/provider/{$provider->code}/users/create")
            ->assertOk();
    }

    public function test_admin_can_see_traffic_online_and_kick_queue_pages(): void
    {
        $admin = Admin::factory()->create();
        $provider = Provider::factory()->create(['code' => 'alpha01']);
        $node = Node::factory()->create(['provider_id' => $provider->id, 'name' => 'node-alpha']);

        \App\Models\NodeUserTraffic::query()->create([
            'node_id' => $node->id, 'user_id' => 'wang', 'day' => now()->toDateString(),
            'tx_bytes' => 1024, 'rx_bytes' => 2048,
        ]);
        \App\Models\NodeUserOnline::query()->create([
            'node_id' => $node->id, 'user_id' => 'wang', 'connections' => 2, 'seen_at' => now(),
        ]);
        \App\Models\NodeKickQueue::query()->create([
            'uuid' => 'wang', 'reason' => 'quota', 'status' => \App\Models\NodeKickQueue::STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'admin')->get('/admin/user-traffic')->assertOk()->assertSee('wang');
        $this->actingAs($admin, 'admin')->get('/admin/online-users')->assertOk()->assertSee('wang');
        $this->actingAs($admin, 'admin')->get('/admin/kick-queue')->assertOk()->assertSee('wang');
        $this->actingAs($admin, 'admin')->get('/admin/kick-queue/create')->assertOk();
    }

    public function test_admin_cannot_enter_provider_panel(): void
    {
        $admin = Admin::factory()->create();
        $provider = Provider::factory()->create(['code' => 'alpha01']);

        // 两个面板用不同 guard：管理端账号在服务商面板里没登录 → 被重定向去服务商登录页
        $this->actingAs($admin, 'admin')
            ->get("/provider/{$provider->code}")
            ->assertRedirect('/provider/login');
    }

    public function test_provider_landing_redirects_to_own_tenant(): void
    {
        $provider = Provider::factory()->create(['code' => 'alpha01']);
        $user = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        // Filament 的 RedirectToTenantController：不带租户的 /provider 会跳到 /provider/{code}
        $this->actingAs($user, 'provider')
            ->get('/provider')
            ->assertRedirect("/provider/{$provider->code}");
    }

    public function test_provider_cannot_enter_admin_panel(): void
    {
        $provider = Provider::factory()->create(['code' => 'alpha01']);
        $providerUser = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        $this->actingAs($providerUser, 'provider')
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }
}
