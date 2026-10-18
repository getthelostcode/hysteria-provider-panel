<?php

namespace Tests\Feature;

use App\Models\EndUser;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HysteriaAuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): EndUser
    {
        $provider = Provider::factory()->create([
            'code' => 'alpha01',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        return EndUser::query()->create(array_merge([
            'provider_id' => $provider->id,
            'email' => 'wang@example.com',
            'password' => 'secret123',
            'status' => EndUser::STATUS_ACTIVE,
        ], $attrs));
    }

    private function payload(string $login, string $password): array
    {
        return [
            'addr' => '1.2.3.4:5678',
            'auth' => base64_encode($login . ':' . $password),
            'tx' => 0,
            'rx' => 0,
        ];
    }

    public function test_valid_credentials_return_uuid(): void
    {
        $user = $this->user();

        $this->postJson('/auth', $this->payload('wang@example.com', 'secret123'))
            ->assertOk()
            ->assertJson(['ok' => true, 'id' => $user->uuid]);

        $this->assertNotNull($user->fresh()->last_auth_at);
    }

    public function test_uuid_can_be_used_as_login(): void
    {
        $user = $this->user();

        $this->postJson('/auth', $this->payload($user->uuid, 'secret123'))
            ->assertOk()
            ->assertJson(['ok' => true, 'id' => $user->uuid]);
    }

    public function test_plain_auth_without_base64_is_accepted(): void
    {
        $this->user();

        $this->postJson('/auth', [
            'addr' => '1.2.3.4:1',
            'auth' => 'wang@example.com:secret123',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->user();

        $this->postJson('/auth', $this->payload('wang@example.com', 'wrongpass'))
            ->assertOk()
            ->assertJsonPath('ok', false);
    }

    public function test_unknown_user_is_rejected(): void
    {
        $this->postJson('/auth', $this->payload('ghost@example.com', 'secret123'))
            ->assertOk()
            ->assertJsonPath('ok', false);
    }

    public function test_disabled_user_is_rejected(): void
    {
        $this->user(['status' => EndUser::STATUS_DISABLED]);

        $this->postJson('/auth', $this->payload('wang@example.com', 'secret123'))
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('msg', '账号已停用，请联系服务商');
    }

    public function test_suspended_provider_blocks_all_users(): void
    {
        $user = $this->user();
        $user->provider->update(['status' => Provider::STATUS_SUSPENDED]);

        $this->postJson('/auth', $this->payload('wang@example.com', 'secret123'))
            ->assertOk()
            ->assertJsonPath('ok', false);
    }

    public function test_expired_user_is_rejected(): void
    {
        $this->user(['expires_at' => now()->subDay()]);

        $this->postJson('/auth', $this->payload('wang@example.com', 'secret123'))
            ->assertOk()
            ->assertJsonPath('ok', false);
    }

    public function test_missing_auth_returns_not_ok(): void
    {
        $this->postJson('/auth', ['addr' => '1.2.3.4:1'])
            ->assertOk()
            ->assertJsonPath('ok', false);
    }
}
