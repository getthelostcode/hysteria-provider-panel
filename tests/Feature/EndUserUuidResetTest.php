<?php

namespace Tests\Feature;

use App\Filament\Provider\Resources\EndUserResource\Pages\ListEndUsers as ProviderListEndUsers;
use App\Filament\Resources\EndUserResource\Pages\ListEndUsers as AdminListEndUsers;
use App\Models\Admin;
use App\Models\EndUser;
use App\Models\Node;
use App\Models\NodeUserOnline;
use App\Models\NodeUserTraffic;
use App\Models\Provider;
use App\Models\ProviderUser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EndUserUuidResetTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $provider = Provider::factory()->create(['code' => 'alpha01', 'name' => '服务商 A']);
        $providerUser = ProviderUser::factory()->create(['provider_id' => $provider->id]);
        $node = Node::factory()->create(['provider_id' => $provider->id]);

        $user = EndUser::query()->create([
            'provider_id' => $provider->id,
            'email' => 'joe@example.com',
            'password' => 'secret123',
            'status' => EndUser::STATUS_ACTIVE,
        ]);

        NodeUserTraffic::query()->create([
            'node_id' => $node->id, 'user_id' => $user->uuid, 'day' => '2026-10-18',
            'tx_bytes' => 1024, 'rx_bytes' => 2048,
        ]);
        NodeUserOnline::query()->create([
            'node_id' => $node->id, 'user_id' => $user->uuid, 'connections' => 2, 'seen_at' => now(),
        ]);

        return [$provider, $providerUser, $node, $user];
    }

    private function authPayload(string $login, string $password): array
    {
        return ['addr' => '1.2.3.4:1', 'auth' => base64_encode($login . ':' . $password)];
    }

    public function test_provider_can_reset_user_uuid_and_history_moves(): void
    {
        [$provider, $providerUser, $node, $user] = $this->fixtures();
        $oldUuid = $user->uuid;
        $oldToken = $user->connect_token;

        Filament::setCurrentPanel(Filament::getPanel('provider'));
        Filament::setTenant($provider, isQuiet: true);

        Livewire::actingAs($providerUser, 'provider')
            ->test(ProviderListEndUsers::class)
            ->callTableAction('reset_uuid', $user->id);

        $user->refresh();
        $this->assertNotSame($oldUuid, $user->uuid);
        $this->assertSame(32, strlen($user->uuid));
        $this->assertNotSame($oldToken, $user->connect_token);

        // 历史流量 / 在线快照跟着迁到新 uuid
        $this->assertSame(0, NodeUserTraffic::query()->where('user_id', $oldUuid)->count());
        $this->assertSame(1, NodeUserTraffic::query()->where('user_id', $user->uuid)->count());
        $this->assertSame(0, NodeUserOnline::query()->where('user_id', $oldUuid)->count());
        $this->assertSame(1, NodeUserOnline::query()->where('user_id', $user->uuid)->count());

        // 旧凭据立即失效，新 uuid 可用
        $this->postJson('/auth', $this->authPayload($oldUuid, $oldUuid))->assertJsonPath('ok', false);
        $this->postJson('/auth', $this->authPayload($user->uuid, $user->uuid))
            ->assertJson(['ok' => true, 'id' => $user->uuid]);
    }

    public function test_admin_can_reset_user_uuid(): void
    {
        [$provider, , , $user] = $this->fixtures();
        $admin = Admin::factory()->create();
        $old = $user->uuid;

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin, 'admin')
            ->test(AdminListEndUsers::class)
            ->callTableAction('reset_uuid', $user->id);

        $this->assertNotSame($old, $user->fresh()->uuid);

        // 邮箱 + 密码仍可登录（门户密码不受影响）
        $this->postJson('/auth', $this->authPayload('joe@example.com', 'secret123'))
            ->assertJsonPath('ok', true);
    }
}
