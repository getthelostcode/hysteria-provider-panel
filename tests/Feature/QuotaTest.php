<?php

namespace Tests\Feature;

use App\Filament\Join\Pages\Register;
use App\Filament\Provider\Pages\QuotaSettings;
use App\Filament\Provider\Resources\EndUserResource;
use App\Filament\Provider\Resources\EndUserResource\Pages\CreateEndUser;
use App\Models\EndUser;
use App\Models\Node;
use App\Models\Provider;
use App\Models\ProviderUser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotaTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 ** 3;

    private const TOKEN = 'collector-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['collector.token' => self::TOKEN]);
    }

    private function provider(array $attrs = []): Provider
    {
        return Provider::factory()->create(array_merge([
            'code' => 'alpha01',
            'status' => Provider::STATUS_ACTIVE,
        ], $attrs));
    }

    private function endUser(Provider $provider, array $attrs = []): EndUser
    {
        return EndUser::query()->create(array_merge([
            'provider_id' => $provider->id,
            'email' => 'u' . fake()->unique()->numberBetween(1, 99999) . '@example.com',
            'password' => 'secret-123',
            'status' => EndUser::STATUS_ACTIVE,
            'traffic_quota_bytes' => 10 * self::GB,
            'quota_reset_days' => 7,
            'quota_reset_at' => now()->addDays(7),
        ], $attrs));
    }

    public function test_join_registration_applies_provider_default_quota(): void
    {
        $provider = $this->provider([
            'default_quota_bytes' => 10 * self::GB,
            'default_quota_reset_days' => 7,
        ]);

        Livewire::test(Register::class, ['code' => $provider->code])
            ->fillForm([
                'name' => '小李',
                'email' => 'join@example.com',
                'password' => 'secret-123',
                'password_confirmation' => 'secret-123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = EndUser::query()->where('email', 'join@example.com')->firstOrFail();

        $this->assertSame(10 * self::GB, $user->traffic_quota_bytes);
        $this->assertSame(7, $user->quota_reset_days);
        $this->assertNotNull($user->quota_reset_at);
        $this->assertTrue($user->quota_reset_at->between(now()->addDays(6)->addHour(), now()->addDays(8)));
    }

    public function test_ingest_accumulates_quota_and_rolls_over(): void
    {
        $provider = $this->provider();
        $user = $this->endUser($provider, ['quota_reset_at' => now()->subDay()]);
        $node = Node::factory()->create(['provider_id' => $provider->id, 'status' => Node::STATUS_ACTIVE]);

        // 周期已过：先清零再加增量
        $this->postJson('/api/collector/v1/ingest', [
            'node_id' => (string) $node->id,
            'polled_at' => now()->toIso8601String(),
            'users' => [['user_id' => $user->uuid, 'tx' => 1 * self::GB, 'rx' => 1 * self::GB]],
        ], ['X-Collector-Token' => self::TOKEN])->assertOk();

        $user->refresh();
        $this->assertSame(2 * self::GB, $user->quota_used_bytes);
        $this->assertTrue($user->quota_reset_at->isFuture());

        // 同一周期内继续累计
        $this->postJson('/api/collector/v1/ingest', [
            'node_id' => (string) $node->id,
            'polled_at' => now()->addMinute()->toIso8601String(),
            'users' => [['user_id' => $user->uuid, 'tx' => 512 * 1024 ** 2, 'rx' => 0]],
        ], ['X-Collector-Token' => self::TOKEN])->assertOk();

        $this->assertSame((int) (2.5 * self::GB), $user->refresh()->quota_used_bytes);
    }

    public function test_auth_rejects_when_quota_exhausted(): void
    {
        $provider = $this->provider();
        $user = $this->endUser($provider, [
            'traffic_quota_bytes' => 10 * self::GB,
            'quota_used_bytes' => 10 * self::GB,
        ]);

        $this->postJson('/auth', ['addr' => '1.2.3.4:1', 'auth' => $user->uuid])
            ->assertOk()
            ->assertJson(['ok' => false, 'msg' => '流量已用完，请联系服务商']);
    }

    public function test_auth_rolls_over_expired_period_then_allows(): void
    {
        $provider = $this->provider();
        $user = $this->endUser($provider, [
            'traffic_quota_bytes' => 10 * self::GB,
            'quota_used_bytes' => 10 * self::GB,
            'quota_reset_at' => now()->subDay(),
        ]);

        $this->postJson('/auth', ['addr' => '1.2.3.4:1', 'auth' => $user->uuid])
            ->assertOk()
            ->assertJson(['ok' => true, 'id' => $user->uuid]);

        $user->refresh();
        $this->assertSame(0, $user->quota_used_bytes);
        $this->assertTrue($user->quota_reset_at->isFuture());
    }

    public function test_provider_sets_default_quota_for_new_users(): void
    {
        $provider = $this->provider();
        $owner = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        $this->actingAs($owner, 'provider');
        Filament::setCurrentPanel(Filament::getPanel('provider'));
        Filament::setTenant($provider);

        Livewire::actingAs($owner, 'provider')
            ->test(QuotaSettings::class)
            ->fillForm([
                'default_quota_gb' => 10,
                'default_reset_days' => 7,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $provider->refresh();
        $this->assertSame(10 * self::GB, $provider->default_quota_bytes);
        $this->assertSame(7, $provider->default_quota_reset_days);
    }

    public function test_user_form_converts_gb_to_bytes(): void
    {
        $merged = EndUserResource::mergeQuotaInput([
            'quota_gb' => 10,
            'quota_reset_days' => 7,
        ]);

        $this->assertSame(10 * self::GB, $merged['traffic_quota_bytes']);
        $this->assertSame(7, $merged['quota_reset_days']);
        $this->assertNotNull($merged['quota_reset_at']);
        $this->assertArrayNotHasKey('quota_gb', $merged);

        $cleared = EndUserResource::mergeQuotaInput(['quota_gb' => null, 'quota_reset_days' => null]);
        $this->assertNull($cleared['traffic_quota_bytes']);
        $this->assertNull($cleared['quota_reset_days']);
        $this->assertNull($cleared['quota_reset_at']);
    }

    public function test_quota_usage_labels(): void
    {
        $provider = $this->provider();
        $user = $this->endUser($provider, [
            'traffic_quota_bytes' => 10 * self::GB,
            'quota_used_bytes' => (int) (6.5 * self::GB),
        ]);

        $this->assertSame('6.5 GB / 10 GB', $user->quotaUsageLabel());
        $this->assertFalse($user->quotaExceeded());
        $this->assertSame((int) (3.5 * self::GB), $user->quotaRemainingBytes());
    }

    public function test_users_page_links_to_quota_settings(): void
    {
        $provider = $this->provider();
        $owner = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        $this->actingAs($owner, 'provider')
            ->get('/provider/alpha01/users')
            ->assertOk()
            ->assertSee('套餐默认值')
            ->assertSee('/provider/alpha01/quota');
    }

    public function test_provider_creates_user_with_quota_from_form(): void
    {
        $provider = $this->provider();
        $owner = ProviderUser::factory()->create(['provider_id' => $provider->id]);

        $this->actingAs($owner, 'provider');
        Filament::setCurrentPanel(Filament::getPanel('provider'));
        Filament::setTenant($provider);

        Livewire::test(CreateEndUser::class)
            ->fillForm([
                'email' => 'form-quota@example.com',
                'password' => 'secret-123',
                'status' => EndUser::STATUS_ACTIVE,
                'quota_gb' => 10,
                'quota_reset_days' => 7,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = EndUser::query()->where('email', 'form-quota@example.com')->firstOrFail();

        // 回归：quota_gb 必须落成 traffic_quota_bytes（曾因 dehydrated(false) 被丢掉）
        $this->assertSame(10 * self::GB, $user->traffic_quota_bytes);
        $this->assertSame(7, $user->quota_reset_days);
        $this->assertNotNull($user->quota_reset_at);
    }
}
