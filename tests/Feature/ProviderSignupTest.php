<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProviderSignupTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '星云网络',
            'code' => 'xingyun01',
            'email' => 'owner@xingyun.test',
            'contact' => 'tg:@xingyun',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
        ], $overrides);
    }

    public function test_signup_page_is_public(): void
    {
        $this->get('/provider-signup')
            ->assertOk()
            ->assertSee('注册服务商')
            ->assertSee('登录账号')
            ->assertSee('识别码');
    }

    public function test_signup_creates_provider_and_login_and_logs_in(): void
    {
        $response = $this->post('/provider-signup', $this->payload());

        $response->assertRedirect('/provider/xingyun01');

        $provider = Provider::query()->where('code', 'xingyun01')->firstOrFail();
        $this->assertSame('星云网络', $provider->name);
        $this->assertSame(Provider::STATUS_ACTIVE, $provider->status);

        $user = ProviderUser::query()->where('email', 'owner@xingyun.test')->firstOrFail();
        $this->assertSame($provider->id, $user->provider_id);
        $this->assertTrue(Hash::check('secret-123', $user->password));
        $this->assertNotSame('secret-123', $user->password);

        $this->assertAuthenticatedAs($user, 'provider');
    }

    public function test_code_is_auto_generated_when_blank(): void
    {
        $this->post('/provider-signup', $this->payload(['code' => '']))
            ->assertRedirect();

        $provider = Provider::query()->sole();
        $this->assertMatchesRegularExpression('/^p[a-z0-9]{10}$/', $provider->code);
        $this->assertAuthenticated('provider');
    }

    public function test_code_is_normalized_to_lowercase(): void
    {
        $this->post('/provider-signup', $this->payload(['code' => 'XingYun01']))
            ->assertRedirect('/provider/xingyun01');

        $this->assertTrue(Provider::query()->where('code', 'xingyun01')->exists());
    }

    public function test_duplicate_code_is_rejected(): void
    {
        Provider::factory()->create(['code' => 'xingyun01']);

        $this->post('/provider-signup', $this->payload())
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Provider::query()->count());
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->post('/provider-signup', $this->payload())->assertRedirect();

        $this->post('/provider-signup', $this->payload(['code' => 'another01']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Provider::query()->count());
    }

    public function test_weak_password_and_mismatch_are_rejected(): void
    {
        $this->post('/provider-signup', $this->payload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');

        $this->post('/provider-signup', $this->payload(['password_confirmation' => 'different-123']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Provider::query()->count());
    }

    public function test_bad_code_format_is_rejected(): void
    {
        foreach (['ab', '中文识别码', '-abc', 'has space'] as $code) {
            $this->post('/provider-signup', $this->payload(['code' => $code]))
                ->assertSessionHasErrors('code');
        }

        $this->assertSame(0, Provider::query()->count());
    }

    public function test_signup_can_be_disabled(): void
    {
        config(['hysteria.provider_signup_enabled' => false]);

        $this->get('/provider-signup')->assertNotFound();
        $this->post('/provider-signup', $this->payload())->assertNotFound();

        $this->assertSame(0, Provider::query()->count());
    }

    public function test_login_page_links_to_signup(): void
    {
        $this->get('/provider/login')
            ->assertOk()
            ->assertSee('注册服务商');
    }
}
