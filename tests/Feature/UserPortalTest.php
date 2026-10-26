<?php

namespace Tests\Feature;

use App\Filament\User\Pages\Dashboard;
use App\Filament\User\Pages\Servers;
use App\Models\EndUser;
use App\Models\Node;
use App\Models\NodeUserOnline;
use App\Models\NodeUserTraffic;
use App\Models\Provider;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserPortalTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;

    private Node $nodeA;

    private Node $nodeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create([
            'code' => 'alpha01',
            'name' => '示例服务商 A',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        $this->nodeA = Node::factory()->create([
            'provider_id' => $this->provider->id,
            'name' => '东京-01',
            'hostname' => 'jp1.example.com',
            'last_seen_at' => now(),
        ]);

        $this->nodeB = Node::factory()->create([
            'provider_id' => $this->provider->id,
            'name' => '大阪-02',
            'hostname' => 'os2.example.com',
            'last_seen_at' => now(),
        ]);
    }

    private function user(string $email = 'joe@example.com', array $attrs = []): EndUser
    {
        return EndUser::query()->create(array_merge([
            'provider_id' => $this->provider->id,
            'email' => $email,
            'password' => 'secret123',
            'status' => EndUser::STATUS_ACTIVE,
        ], $attrs));
    }

    public function test_login_page_renders(): void
    {
        $this->get('/user/login')->assertOk()->assertSee('登录');
    }

    public function test_user_can_login_with_email_and_password(): void
    {
        $user = $this->user();

        Filament::setCurrentPanel(Filament::getPanel('user'));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'joe@example.com', 'password' => 'secret123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user, 'end_user');
    }

    public function test_wrong_password_cannot_login(): void
    {
        $this->user();

        Filament::setCurrentPanel(Filament::getPanel('user'));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'joe@example.com', 'password' => 'wrongpass'])
            ->call('authenticate')
            ->assertHasFormErrors();

        $this->assertGuest('end_user');
    }

    public function test_dashboard_shows_account_and_totals(): void
    {
        $user = $this->user();

        NodeUserTraffic::query()->create([
            'node_id' => $this->nodeA->id, 'user_id' => $user->uuid, 'day' => now()->toDateString(),
            'tx_bytes' => 1024 ** 2, 'rx_bytes' => 2 * 1024 ** 2,
        ]);

        $this->actingAs($user, 'end_user')->get('/user')
            ->assertOk()
            ->assertSee('累计流量')
            ->assertSee('3 MB')
            ->assertSee('示例服务商 A')
            ->assertSee('joe@example.com');
    }

    public function test_usage_page_lists_only_my_records(): void
    {
        $me = $this->user();
        $other = $this->user('other@example.com');

        NodeUserTraffic::query()->create([
            'node_id' => $this->nodeA->id, 'user_id' => $me->uuid, 'day' => '2026-10-17',
            'tx_bytes' => 1024 ** 2, 'rx_bytes' => 1024 ** 2,
        ]);
        NodeUserTraffic::query()->create([
            'node_id' => $this->nodeB->id, 'user_id' => $me->uuid, 'day' => '2026-10-18',
            'tx_bytes' => 2 * 1024 ** 2, 'rx_bytes' => 2 * 1024 ** 2,
        ]);
        NodeUserTraffic::query()->create([
            'node_id' => $this->nodeA->id, 'user_id' => $other->uuid, 'day' => '2026-10-18',
            'tx_bytes' => 500 * 1024 ** 2, 'rx_bytes' => 500 * 1024 ** 2,
        ]);

        $this->actingAs($me, 'end_user')->get('/user/usage')
            ->assertOk()
            ->assertSee('东京-01')
            ->assertSee('大阪-02')
            ->assertSee('2026-10-17')
            ->assertSee('2.00 MB')   // 10-17 合计 1+1
            ->assertSee('4.00 MB')   // 10-18 合计 2+2
            ->assertDontSee('500.00 MB')   // 别人的用量不出现
            ->assertDontSee('1000.00 MB');
    }

    public function test_servers_page_shows_my_traffic_per_node(): void
    {
        $me = $this->user();

        NodeUserTraffic::query()->create([
            'node_id' => $this->nodeA->id, 'user_id' => $me->uuid, 'day' => '2026-10-17',
            'tx_bytes' => 1024 ** 2, 'rx_bytes' => 1024 ** 2,
        ]);
        NodeUserTraffic::query()->create([
            'node_id' => $this->nodeA->id, 'user_id' => $me->uuid, 'day' => '2026-10-18',
            'tx_bytes' => 2 * 1024 ** 2, 'rx_bytes' => 2 * 1024 ** 2,
        ]);
        NodeUserOnline::query()->create([
            'node_id' => $this->nodeA->id, 'user_id' => $me->uuid, 'connections' => 2, 'seen_at' => now(),
        ]);

        $this->actingAs($me, 'end_user')->get('/user/servers')
            ->assertOk()
            ->assertSee($this->nodeA->ip)
            ->assertSee($this->nodeB->ip)
            ->assertSee('东京-01')
            ->assertSee('大阪-02')
            ->assertSee('我的合计')
            ->assertSee('连接 / 二维码')
            ->assertSee('6.00 MB')    // 东京-01 上我的 3MB + 3MB
            ->assertSee('0 B');       // 大阪-02 没用过
    }

    public function test_server_action_renders_uri_and_qr(): void
    {
        $me = $this->user();

        $component = Livewire::actingAs($me, 'end_user')->test(Servers::class);
        $component->mountTableAction('connect', $this->nodeA->id);

        $html = $component->html();
        $this->assertStringContainsString('hysteria2://', $html);
        $this->assertStringContainsString($me->uuid, $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('obfs-password=alpha01', $html);
        // 连接地址以 IP 为准，不用 hostname
        $this->assertStringContainsString('@' . $this->nodeA->ip . ':', $html);
        $this->assertStringNotContainsString('@jp1.example.com', $html);
        // 复制按钮：不再把 JSON 塞进双引号属性（会截断），且有 execCommand 降级
        $this->assertStringContainsString('x-on:click="copy()"', $html);
        $this->assertStringContainsString('execCommand', $html);
        $this->assertStringNotContainsString('writeText(&quot;', $html);
    }

    public function test_disabled_user_cannot_access_portal(): void
    {
        $user = $this->user(attrs: ['status' => EndUser::STATUS_DISABLED]);

        $this->actingAs($user, 'end_user')->get('/user')->assertForbidden();
        $this->actingAs($user, 'end_user')->get('/user/usage')->assertForbidden();
    }

    public function test_suspended_provider_blocks_portal(): void
    {
        $user = $this->user();
        $this->provider->update(['status' => Provider::STATUS_SUSPENDED]);

        $this->actingAs($user, 'end_user')->get('/user')->assertForbidden();
    }

    public function test_portal_is_provider_scoped(): void
    {
        $otherProvider = Provider::factory()->create(['code' => 'beta02', 'name' => '另一家']);
        $foreign = Node::factory()->create([
            'provider_id' => $otherProvider->id,
            'name' => '别家节点',
            'hostname' => 'other.example.com',
        ]);

        $me = $this->user();
        NodeUserTraffic::query()->create([
            'node_id' => $foreign->id, 'user_id' => $me->uuid, 'day' => '2026-10-18',
            'tx_bytes' => 1024 ** 3, 'rx_bytes' => 0,
        ]);

        // 自己运营商之外的节点不出现在服务器页
        $this->actingAs($me, 'end_user')->get('/user/servers')
            ->assertOk()
            ->assertDontSee('别家节点');

        // Dashboard 路由名以 . 结尾（slug '/'），只用得到路径，这里顺带断言可访问
        $this->assertSame(url('/user'), Dashboard::getUrl());
    }

    public function test_dashboard_shows_quota_usage_ratio(): void
    {
        $user = $this->user(attrs: [
            'traffic_quota_bytes' => 10 * 1024 ** 3,
            'quota_used_bytes' => (int) (2.5 * 1024 ** 3),
            'quota_reset_days' => 7,
            'quota_reset_at' => now()->addDays(3),
        ]);

        $this->actingAs($user, 'end_user')->get('/user')
            ->assertOk()
            ->assertSee('流量使用')
            ->assertSee('25%')
            ->assertSee('2.5 GB / 10 GB', false)
            ->assertSee('剩余 7.5 GB', false);
    }

    public function test_dashboard_without_quota_shows_used_only(): void
    {
        $user = $this->user(attrs: ['quota_used_bytes' => (int) (1.5 * 1024 ** 3)]);

        $this->actingAs($user, 'end_user')->get('/user')
            ->assertOk()
            ->assertSee('不限流量')
            ->assertSee('1.5 GB', false)
            ->assertDontSee('剩余');
    }
}
