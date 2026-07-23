<?php

declare(strict_types=1);

use App\Domain\Audit\ActivityLogger;
use App\Domain\Security\AddressBookService;
use App\Domain\Security\AuditChain;
use App\Domain\Security\Contracts\GeoLocator;
use App\Domain\Security\Contracts\IpReputationProvider;
use App\Domain\Security\SuspiciousLoginDetector;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Enums\KycTier;
use App\Enums\WithdrawalStatus;
use App\Models\AddressBookEntry;
use App\Models\Admin;
use App\Models\LoginHistory;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;

function secOperator(): Admin
{
    $admin = Admin::create([
        'name' => 'SecOp', 'email' => 'secop@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $admin->syncRoles(['super-admin']);

    return $admin;
}

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->user->forceFill(['created_at' => now()->subMonth()])->save();
    creditUser($this->user, $this->asset, '100000000'); // 100 USDT
});

function withdrawReq(): RequestWithdrawalAction
{
    return app(RequestWithdrawalAction::class);
}

// ---------------------------------------------------------------------------
// 1. Withdrawal address whitelist
// ---------------------------------------------------------------------------
it('blocks a withdrawal to a non-whitelisted address when the whitelist is on', function () {
    updateSetting('security_withdrawal_whitelist', true, 'security');

    withdrawReq()->execute($this->user, $this->asset, $this->asset->money('1000000'), 'TnotListed', 'wl-1');
})->throws(ValidationException::class);

it('allows a withdrawal to a whitelisted, matured address', function () {
    updateSetting('security_withdrawal_whitelist', true, 'security');
    AddressBookEntry::create([
        'user_id' => $this->user->id, 'label' => 'Trusted', 'chain_id' => $this->asset->chain_id,
        'address' => 'Ttrusted', 'status' => 'active', 'whitelisted_at' => now(),
    ]);

    $w = withdrawReq()->execute($this->user, $this->asset, $this->asset->money('1000000'), 'Ttrusted', 'wl-2');

    expect($w)->toBeInstanceOf(Withdrawal::class)->and($w->to_address)->toBe('Ttrusted');
});

it('does not enforce the whitelist when the flag is off (backward compatible)', function () {
    $w = withdrawReq()->execute($this->user, $this->asset, $this->asset->money('1000000'), 'Tanything', 'wl-3');
    expect($w)->toBeInstanceOf(Withdrawal::class);
});

// ---------------------------------------------------------------------------
// 2. Address cooldown
// ---------------------------------------------------------------------------
it('puts a newly added address into cooldown, then matures it', function () {
    updateSetting('security_address_cooldown', true, 'security');
    updateSetting('security_address_cooldown_hours', 24, 'security');
    $svc = app(AddressBookService::class);

    $entry = $svc->add($this->user, 'Tnew', 'My addr', $this->asset->chain_id);
    expect($entry->status)->toBe('pending')
        ->and($entry->inCooldown())->toBeTrue()
        ->and($entry->isWhitelisted())->toBeFalse();

    // Fast-forward past the cooldown and mature it.
    $entry->forceFill(['cooldown_until' => now()->subHour()])->save();
    $svc->promoteMatured($this->user);

    expect($entry->fresh()->status)->toBe('active')
        ->and($entry->fresh()->isWhitelisted())->toBeTrue();
});

// ---------------------------------------------------------------------------
// 3. Velocity limits
// ---------------------------------------------------------------------------
it('forces manual review once the daily withdrawal velocity cap is hit', function () {
    updateSetting('security_velocity_limits', true, 'security');
    updateSetting('security_daily_withdrawal_count', 1, 'security');

    $first = withdrawReq()->execute($this->user, $this->asset, $this->asset->money('1000000'), 'Tvel', 'v-1');
    $second = withdrawReq()->execute($this->user, $this->asset, $this->asset->money('1000000'), 'Tvel', 'v-2');

    expect($second->status)->toBe(WithdrawalStatus::Review)
        ->and(SecurityEvent::where('type', 'velocity_exceeded')->where('user_id', $this->user->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// 4. Suspicious login detection (+ device fingerprinting, IP/geo enrichment)
// ---------------------------------------------------------------------------
function loginRequest(string $ip, string $ua): Request
{
    return Request::create('/login', 'POST', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $ua]);
}

it('records login history and flags a new device on an established account', function () {
    $detector = app(SuspiciousLoginDetector::class);

    // First sign-in: no prior history → no new-device alert.
    $detector->inspect($this->user, loginRequest('1.1.1.1', 'Chrome'));
    expect(LoginHistory::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(SecurityEvent::where('type', 'new_device')->where('user_id', $this->user->id)->count())->toBe(0);

    // Second sign-in from a different device on an now-established account.
    $detector->inspect($this->user, loginRequest('2.2.2.2', 'Firefox'));
    expect(LoginHistory::where('user_id', $this->user->id)->count())->toBe(2)
        ->and(SecurityEvent::where('type', 'new_device')->where('user_id', $this->user->id)->count())->toBe(1);
});

it('flags a denylisted IP via the reputation adapter during enrichment', function () {
    updateSetting('security_ip_denylist', ['9.9.9.9'], 'security');

    app(SuspiciousLoginDetector::class)->inspect($this->user, loginRequest('9.9.9.9', 'Chrome'));

    $event = SecurityEvent::where('type', 'ip_flagged')->where('user_id', $this->user->id)->first();
    expect($event)->not->toBeNull()->and($event->severity)->toBe('critical')
        ->and(LoginHistory::where('user_id', $this->user->id)->first()->risk_score)->toBeGreaterThanOrEqual(40);
});

// ---------------------------------------------------------------------------
// 5. IP reputation + geo adapters (provider-independent)
// ---------------------------------------------------------------------------
it('resolves the IP reputation + geo adapters and honours the denylist', function () {
    updateSetting('security_ip_denylist', ['6.6.6.6'], 'security');

    expect(app(IpReputationProvider::class)->check('6.6.6.6')->isRisky())->toBeTrue()
        ->and(app(IpReputationProvider::class)->check('1.2.3.4')->isRisky())->toBeFalse()
        ->and(app(GeoLocator::class)->locate('1.2.3.4')->isKnown())->toBeFalse();
});

// ---------------------------------------------------------------------------
// 6. Immutable audit logs (hash chain)
// ---------------------------------------------------------------------------
it('builds a verifiable audit hash chain and detects tampering', function () {
    ActivityLogger::log('test.a', null, ['x' => 1]);
    ActivityLogger::log('test.b', null, ['x' => 2]);
    ActivityLogger::log('test.c', null, ['x' => 3]);

    $before = AuditChain::verify();
    expect($before['ok'])->toBeTrue()->and($before['count'])->toBeGreaterThanOrEqual(3);

    // Tamper with a row's payload directly, bypassing the model.
    $row = DB::table('audit_logs')->where('action', 'test.b')->first();
    DB::table('audit_logs')->where('id', $row->id)->update(['action' => 'test.hacked']);

    expect(AuditChain::verify()['ok'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// 7. User security centre (UI + actions)
// ---------------------------------------------------------------------------
it('renders the user security page and manages addresses + anti-phishing', function () {
    // The security centre now lives under Settings › Security; the old URL redirects.
    actingAs($this->user)->get(route('security.index'))->assertRedirect(route('settings.index', ['tab' => 'security']));
    actingAs($this->user)->get(route('settings.index', ['tab' => 'security']))->assertOk()->assertSee('Withdrawal addresses');

    actingAs($this->user)->post(route('security.address.add'), ['address' => 'Tuser', 'label' => 'Home'])
        ->assertRedirect();
    expect(AddressBookEntry::where('user_id', $this->user->id)->where('address', 'Tuser')->exists())->toBeTrue();

    actingAs($this->user)->put(route('security.anti-phishing'), ['anti_phishing_code' => 'blue-otter'])
        ->assertRedirect();
    expect($this->user->fresh()->anti_phishing_code)->toBe('blue-otter');
});

// ---------------------------------------------------------------------------
// 8. REST API
// ---------------------------------------------------------------------------
it('exposes the security centre over the REST API', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/security/addresses', ['address' => 'Tapi'])->assertCreated();
    $this->getJson('/api/v1/security/addresses')->assertOk()->assertJsonFragment(['address' => 'Tapi']);
    $this->getJson('/api/v1/security/events')->assertOk();
    $this->getJson('/api/v1/security/login-history')->assertOk();
});

// ---------------------------------------------------------------------------
// 9. Admin security monitoring dashboard
// ---------------------------------------------------------------------------
it('renders the admin security dashboard and toggles a module flag', function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $admin = secOperator();

    actingAs($admin, 'admin')->get(route('admin.security'))->assertOk()->assertSee('Security Monitoring');

    actingAs($admin, 'admin')->post(route('admin.security.flag'), ['flag' => 'suspicious_login'])->assertRedirect();
    expect(feature('security_suspicious_login', true))->toBeFalse();

    actingAs($admin, 'admin')->post(route('admin.security.verify-chain'))->assertRedirect();
});
