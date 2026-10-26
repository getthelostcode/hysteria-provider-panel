<?php

namespace Tests\Feature;

use App\Filament\Provider\Pages\Profile;
use App\Models\Provider;
use App\Models\ProviderUser;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderProfileTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $providerAttrs = [], array $userAttrs = []): array
    {
        $provider = Provider::factory()->create(array_merge([
            'code' => 'alpha01',
            'name' => '星云网络',
            'contact' => 'tg:@xingyun',
            'status' => Provider::STATUS_ACTIVE,
        ], $providerAttrs));

        $owner = ProviderUser::factory()->create(array_merge([
            'provider_id' => $provider->id,
            'email' => 'owner@xingyun.test',
            'password' => 'secret-123',
        ], $userAttrs));

        return [$provider, $owner];
    }

    private function asProvider(Provider $provider, ProviderUser $owner): void
    {
        $this->actingAs($owner, 'provider');
        Filament::setCurrentPanel(Filament::getPanel('provider'));
        Filament::setTenant($provider);
    }

    public function test_page_requires_login(): void
    {
        $this->make();

        $this->get('/provider/alpha01/profile')->assertRedirect('/provider/login');
    }

    public function test_page_shows_current_profile(): void
    {
        [$provider, $owner] = $this->make();

        $this->actingAs($owner, 'provider')
            ->get("/provider/{$provider->code}/profile")
            ->assertOk()
            ->assertSee('账号资料')
            ->assertSee('运营商名称')
            ->assertSee('星云网络')
            ->assertSee('owner@xingyun.test')
            ->assertSee('重置密码');
    }

    public function test_can_update_name_contact_and_email(): void
    {
        [$provider, $owner] = $this->make();
        $this->asProvider($provider, $owner);

        Livewire::test(Profile::class)
            ->fillForm([
                'name' => '星云网络（新）',
                'contact' => 'tg:@new',
                'account_name' => '小王',
                'email' => 'new-owner@xingyun.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $provider->refresh();
        $this->assertSame('星云网络（新）', $provider->name);
        $this->assertSame('tg:@new', $provider->contact);
        $this->assertSame('new-owner@xingyun.test', $owner->refresh()->email);
        $this->assertSame('小王', $owner->refresh()->name);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        Provider::factory()->create(['code' => 'other01', 'name' => '已存在的名字']);
        [$provider, $owner] = $this->make();
        $this->asProvider($provider, $owner);

        Livewire::test(Profile::class)
            ->fillForm([
                'name' => '已存在的名字',
                'account_name' => '小王',
                'email' => 'owner@xingyun.test',
            ])
            ->call('save')
            ->assertHasFormErrors(['name']);

        $this->assertSame('星云网络', $provider->refresh()->name);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $otherProvider = Provider::factory()->create(['code' => 'other02', 'name' => '别家']);
        ProviderUser::factory()->create(['provider_id' => $otherProvider->id, 'email' => 'taken@example.com']);

        [$provider, $owner] = $this->make();
        $this->asProvider($provider, $owner);

        Livewire::test(Profile::class)
            ->fillForm([
                'name' => '星云网络',
                'account_name' => '小王',
                'email' => 'taken@example.com',
            ])
            ->call('save')
            ->assertHasFormErrors(['email']);

        $this->assertSame('owner@xingyun.test', $owner->refresh()->email);
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        [$provider, $owner] = $this->make();
        $this->asProvider($provider, $owner);

        Livewire::test(Profile::class)
            ->fillForm([
                'name' => '星云网络',
                'account_name' => '小王',
                'email' => 'owner@xingyun.test',
                'current_password' => 'wrong-pass',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        $this->assertTrue(Hash::check('secret-123', $owner->refresh()->password));
    }

    public function test_password_can_be_reset_with_current_password(): void
    {
        [$provider, $owner] = $this->make();
        $this->asProvider($provider, $owner);

        Livewire::test(Profile::class)
            ->fillForm([
                'name' => '星云网络',
                'account_name' => '小王',
                'email' => 'owner@xingyun.test',
                'current_password' => 'secret-123',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('new-secret-123', $owner->refresh()->password));
    }

    public function test_name_is_unique_at_database_level(): void
    {
        $this->make();

        $this->expectException(QueryException::class);

        Provider::factory()->create(['code' => 'dupe01', 'name' => '星云网络']);
    }

    public function test_account_name_is_used_in_user_menu(): void
    {
        [$provider, $owner] = $this->make([], ['name' => '老名字']);
        $this->asProvider($provider, $owner);

        Livewire::test(Profile::class)
            ->fillForm([
                'name' => '星云网络',
                'account_name' => '王工',
                'email' => 'owner@xingyun.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('王工', $owner->refresh()->name);

        $this->actingAs($owner, 'provider')
            ->get("/provider/{$provider->code}")
            ->assertOk()
            ->assertSee('王工');
    }
}
