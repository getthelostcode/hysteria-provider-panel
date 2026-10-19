<?php

namespace Tests\Feature;

use App\Filament\Pages\AuthEndpoint;
use App\Models\Admin;
use App\Models\EndUser;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthEndpointPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(): EndUser
    {
        $provider = Provider::factory()->create(['code' => 'alpha01', 'status' => Provider::STATUS_ACTIVE]);

        return EndUser::query()->create([
            'provider_id' => $provider->id,
            'email' => 'joe@example.com',
            'password' => 'secret123',
            'status' => EndUser::STATUS_ACTIVE,
        ]);
    }

    public function test_requires_admin_login(): void
    {
        $this->get('/admin/auth-endpoint')->assertRedirect('/admin/login');
    }

    public function test_page_shows_endpoint_and_node_config(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->get('/admin/auth-endpoint')
            ->assertOk()
            ->assertSee('/auth')
            ->assertSee('https://auth.ipx.sarl/auth')
            ->assertSee('鉴权接口')
            ->assertSee('auth:')
            ->assertSee('type: http');
    }

    public function test_endpoint_falls_back_to_auth_domain_without_config_key(): void
    {
        config(['hysteria.auth_url' => null]);

        $this->assertSame('https://auth.ipx.sarl/auth', AuthEndpoint::endpoint());
    }

    public function test_endpoint_respects_env_override(): void
    {
        config(['hysteria.auth_url' => 'https://custom.example/auth']);

        $this->assertSame('https://custom.example/auth', AuthEndpoint::endpoint());
    }

    public function test_empty_auth_proves_endpoint_alive(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(AuthEndpoint::class)
            ->call('test')
            ->assertSet('result.ok', false)
            ->assertSet('result.msg', 'missing auth');
    }

    public function test_uuid_only_auth_succeeds(): void
    {
        $admin = Admin::factory()->create();
        $user = $this->user();

        Livewire::actingAs($admin, 'admin')
            ->test(AuthEndpoint::class)
            ->fillForm(['login' => $user->uuid])
            ->call('test')
            ->assertSet('result.ok', true)
            ->assertSet('result.id', $user->uuid);
    }

    public function test_email_password_auth_succeeds(): void
    {
        $admin = Admin::factory()->create();
        $user = $this->user();

        Livewire::actingAs($admin, 'admin')
            ->test(AuthEndpoint::class)
            ->fillForm(['login' => 'joe@example.com', 'password' => 'secret123'])
            ->call('test')
            ->assertSet('result.ok', true)
            ->assertSet('result.id', $user->uuid);
    }

    public function test_wrong_password_fails(): void
    {
        $admin = Admin::factory()->create();
        $this->user();

        Livewire::actingAs($admin, 'admin')
            ->test(AuthEndpoint::class)
            ->fillForm(['login' => 'joe@example.com', 'password' => 'wrongpass'])
            ->call('test')
            ->assertSet('result.ok', false);
    }
}
