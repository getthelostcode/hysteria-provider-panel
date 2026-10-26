<?php

namespace Tests\Feature;

use App\Filament\Resources\ProviderResource\Pages\EditProvider;
use App\Filament\Resources\ProviderResource\RelationManagers\ProviderUsersRelationManager;
use App\Models\Admin;
use App\Models\Provider;
use App\Models\ProviderUser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 平台后台 → 服务商 → 登录账号：改邮箱、改名、重置密码。
 */
class AdminProviderAccountTest extends TestCase
{
    use RefreshDatabase;

    private function make(): array
    {
        $provider = Provider::factory()->create([
            'code' => 'alpha01',
            'name' => '星云网络',
            'status' => Provider::STATUS_ACTIVE,
        ]);
        $user = ProviderUser::factory()->create([
            'provider_id' => $provider->id,
            'email' => 'owner@xingyun.test',
            'name' => '老王',
            'password' => 'old-secret-123',
        ]);

        return [$provider, $user];
    }

    private function asAdmin(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_provider_edit_page_shows_login_account(): void
    {
        [$provider, $user] = $this->make();
        $this->asAdmin();

        $this->get("/admin/providers/{$provider->id}/edit")->assertOk();

        Livewire::test(ProviderUsersRelationManager::class, [
            'ownerRecord' => $provider,
            'pageClass' => EditProvider::class,
        ])
            ->assertCanSeeTableRecords([$user])
            ->assertSee('owner@xingyun.test');
    }

    public function test_admin_can_change_login_email_and_name(): void
    {
        [$provider, $user] = $this->make();
        $this->asAdmin();

        Livewire::test(ProviderUsersRelationManager::class, [
            'ownerRecord' => $provider,
            'pageClass' => EditProvider::class,
        ])
            ->callTableAction('edit', $user, data: [
                'name' => '王工',
                'email' => 'new-owner@xingyun.test',
            ])
            ->assertHasNoTableActionErrors();

        $user->refresh();
        $this->assertSame('new-owner@xingyun.test', $user->email);
        $this->assertSame('王工', $user->name);
    }

    public function test_duplicate_login_email_is_rejected(): void
    {
        [$provider, $user] = $this->make();
        ProviderUser::factory()->create([
            'provider_id' => $provider->id,
            'email' => 'taken@xingyun.test',
        ]);
        $this->asAdmin();

        Livewire::test(ProviderUsersRelationManager::class, [
            'ownerRecord' => $provider,
            'pageClass' => EditProvider::class,
        ])
            ->callTableAction('edit', $user, data: [
                'name' => '老王',
                'email' => 'taken@xingyun.test',
            ])
            ->assertHasTableActionErrors(['email']);

        $this->assertSame('owner@xingyun.test', $user->refresh()->email);
    }

    public function test_admin_can_reset_login_password(): void
    {
        [$provider, $user] = $this->make();
        $this->asAdmin();

        Livewire::test(ProviderUsersRelationManager::class, [
            'ownerRecord' => $provider,
            'pageClass' => EditProvider::class,
        ])
            ->callTableAction('reset_password', $user, data: [
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(Hash::check('new-secret-123', $user->refresh()->password));
        $this->assertFalse(Hash::check('old-secret-123', $user->password));
    }

    public function test_last_login_account_cannot_be_deleted(): void
    {
        [$provider, $user] = $this->make();
        $this->asAdmin();

        Livewire::test(ProviderUsersRelationManager::class, [
            'ownerRecord' => $provider,
            'pageClass' => EditProvider::class,
        ])->assertTableActionHidden('delete', $user);
    }

    public function test_extra_account_can_be_deleted(): void
    {
        [$provider, $user] = $this->make();
        ProviderUser::factory()->create(['provider_id' => $provider->id]);
        $this->asAdmin();

        Livewire::test(ProviderUsersRelationManager::class, [
            'ownerRecord' => $provider,
            'pageClass' => EditProvider::class,
        ])->assertTableActionVisible('delete', $user);
    }
}
