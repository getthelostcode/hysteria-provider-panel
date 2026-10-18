<?php

namespace Tests\Feature;

use App\Models\EndUser;
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
        $this->provider('alpha01');

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
