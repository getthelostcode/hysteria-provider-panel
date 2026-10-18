<?php

namespace Tests\Feature;

use App\Filament\Join\Pages\Register;
use App\Filament\Join\Pages\Welcome;
use App\Models\EndUser;
use App\Models\Node;
use App\Models\NodeUserOnline;
use App\Models\NodeUserTraffic;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class JoinRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $code = 'alpha01', string $name = '运营商 alpha01'): Provider
    {
        return Provider::factory()->create([
            'code' => $code,
            'name' => $name,
            'status' => Provider::STATUS_ACTIVE,
        ]);
    }

    public function test_legacy_url_redirects_to_filament_register_page(): void
    {
        $this->provider('alpha01');

        $this->get('/join/alpha01')->assertRedirect('/join/alpha01/register');
    }

    public function test_register_page_renders_in_filament(): void
    {
        $this->provider('alpha01', '示例服务商 A');

        $this->get('/join/alpha01/register')
            ->assertOk()
            ->assertSee('示例服务商 A')
            ->assertSee('邮箱（登录账号）')
            ->assertSee('注册');
    }

    public function test_register_page_404_for_unknown_or_suspended_provider(): void
    {
        $this->get('/join/nope/register')->assertNotFound();

        Provider::factory()->suspended()->create(['code' => 'beta02', 'name' => '停用的']);
        $this->get('/join/beta02/register')->assertNotFound();
        $this->get('/join/beta02/welcome')->assertNotFound();
    }

    public function test_registration_creates_user_for_provider(): void
    {
        $provider = $this->provider('alpha01');

        Livewire::test(Register::class, ['code' => 'alpha01'])
            ->fillForm([
                'name' => '张三',
                'email' => 'Wang@Example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect('/join/alpha01/welcome');

        $user = EndUser::query()->sole();
        $this->assertSame($provider->id, $user->provider_id);
        $this->assertSame('wang@example.com', $user->email);
        $this->assertSame(32, strlen($user->uuid));
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertSame($user->id, session('join_user_id'));
    }

    public function test_duplicate_email_rejected_across_providers(): void
    {
        $this->provider('alpha01');
        $this->provider('beta02', '运营商 beta02');

        EndUser::query()->create([
            'provider_id' => Provider::query()->where('code', 'alpha01')->value('id'),
            'email' => 'dup@example.com',
            'password' => 'secret123',
            'status' => EndUser::STATUS_ACTIVE,
        ]);

        Livewire::test(Register::class, ['code' => 'beta02'])
            ->fillForm([
                'email' => 'dup@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertSame(1, EndUser::query()->count());
    }

    public function test_password_rules(): void
    {
        $this->provider('alpha01');

        Livewire::test(Register::class, ['code' => 'alpha01'])
            ->fillForm([
                'email' => 'x@example.com',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);

        $this->assertSame(0, EndUser::query()->count());
    }

    public function test_welcome_page_requires_session(): void
    {
        $this->provider('alpha01');

        Livewire::test(Welcome::class, ['code' => 'alpha01'])
            ->assertRedirect('/join/alpha01/register');
    }

    public function test_welcome_lists_provider_servers_with_status_and_counts(): void
    {
        $provider = $this->provider('alpha01');
        $other = $this->provider('beta02', '另一家');

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

        $user = EndUser::query()->create([
            'provider_id' => $provider->id,
            'email' => 'joe@example.com',
            'password' => 'secret123',
            'status' => EndUser::STATUS_ACTIVE,
            'traffic_quota_bytes' => 10 * 1024 ** 3,
            'expires_at' => now()->addDays(30),
        ]);

        NodeUserOnline::query()->create(['node_id' => $online->id, 'user_id' => 'u1', 'connections' => 2, 'seen_at' => now()]);
        NodeUserOnline::query()->create(['node_id' => $online->id, 'user_id' => 'u2', 'connections' => 1, 'seen_at' => now()]);
        NodeUserOnline::query()->create(['node_id' => $offline->id, 'user_id' => 'u3', 'connections' => 4, 'seen_at' => now()->subHour()]);

        NodeUserTraffic::query()->create([
            'node_id' => $online->id, 'user_id' => $user->uuid, 'day' => now()->toDateString(),
            'tx_bytes' => 1024 ** 2, 'rx_bytes' => 3 * 1024 ** 2,
        ]);

        // 走真实 HTTP，连 Filament 面板布局一起渲染
        $this->withSession(['join_user_id' => $user->id])
            ->get('/join/alpha01/welcome')
            ->assertOk()
            ->assertSee('可用服务器')
            ->assertSee('东京-01')
            ->assertSee('jp1.example.com')
            ->assertSee('大阪-02')
            ->assertSee('在线')
            ->assertSee('离线')
            ->assertSee('v2.13.0')
            ->assertSee('10.00 GB')
            ->assertSee('4.00 MB')
            ->assertSee($user->uuid)
            ->assertSee('客户端配置')
            ->assertDontSee('停用的节点')
            ->assertDontSee('别家的节点')
            ->assertDontSee('other.example.com');
    }
}
