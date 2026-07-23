<?php

declare(strict_types=1);

use App\Domain\Merchant\CreateInvoiceAction;
use App\Enums\KycTier;
use App\Models\Admin;
use App\Models\AmlAlert;
use App\Models\Asset;
use App\Models\Card;
use App\Models\ComplianceCase;
use App\Models\DepositMethod;
use App\Models\Merchant;
use App\Models\RewardCampaign;
use App\Models\RewardGrant;
use App\Models\ScreeningResult;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Seed the registry, roles (admin guard) and an operator so pages have data.
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);
});

it('renders public + guest pages', function () {
    $this->get('/')->assertOk();
    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();
    $this->get('/admin/login')->assertOk();
});

it('renders every authenticated user page', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Full]);

    foreach (['dashboard', 'wallet', 'deposit', 'deposits', 'withdraw', 'withdrawals', 'send', 'transfers', 'transactions', 'exchange', 'swaps', 'cards', 'merchant', 'rewards', 'notifications', 'settings'] as $route) {
        actingAs($user)->get(route($route))->assertOk();
    }

    // Each settings section is its own URL (/settings/{tab}).
    foreach (['profile', 'security', 'password', 'verification', 'devices', 'preferences', 'sessions'] as $tab) {
        actingAs($user)->get(route('settings', ['tab' => $tab]))->assertOk();
    }

    // Verification now lives in the Settings "Verification" section.
    actingAs($user)->get(route('kyc'))->assertRedirect(route('settings', ['tab' => 'verification']));
});

it('renders the per-card management page for its owner', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $card = Card::create([
        'user_id' => $user->id, 'program' => 'poisapay-demo', 'type' => 'virtual', 'network' => 'visa',
        'issuer_card_ref' => 'tok_'.str_repeat('d', 24), 'last4' => '7777', 'status' => 'active', 'settlement_currency' => 'USD',
    ]);

    actingAs($user)->get(route('cards.manage', $card))->assertOk();

    // A different user cannot manage someone else's card.
    actingAs(User::factory()->create())->get(route('cards.manage', $card))->assertForbidden();
});

it('renders a per-asset single page', function () {
    $user = User::factory()->create();
    actingAs($user)->get(route('wallet.show', 'USDT'))->assertOk();
});

it('renders the invoice pay page', function () {
    $merchant = User::factory()->create();
    $payer = User::factory()->create();
    $asset = Asset::where('symbol', 'USDT')->first();
    $invoice = app(CreateInvoiceAction::class)->execute(
        $merchant, $asset, Money::ofBase('1000000', 6, 'USDT'), 'SMOKE-1'
    );

    actingAs($payer)->get(route('pay.invoice', $invoice->id))->assertOk();
});

it('renders every admin page for an operator on the admin guard', function () {
    $admin = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $admin->syncRoles(['super-admin']);

    // A real consumer account + card so list/detail pages render with data (not just empty states).
    $customer = User::factory()->create(['kyc_tier' => KycTier::Full]);
    Card::create([
        'user_id' => $customer->id, 'program' => 'poisapay-demo', 'type' => 'virtual', 'network' => 'visa',
        'issuer_card_ref' => 'tok_'.str_repeat('c', 24), 'last4' => '4242', 'status' => 'active', 'settlement_currency' => 'USD',
    ]);
    Merchant::create([
        'user_id' => $customer->id, 'business_name' => 'Smoke Store', 'slug' => 'smoke-store',
        'category' => 'retail', 'status' => 'active', 'approved_at' => now(),
    ]);
    $case = ComplianceCase::create([
        'user_id' => $customer->id, 'status' => 'open', 'risk_level' => 'high', 'reason' => 'velocity',
    ]);
    AmlAlert::create([
        'user_id' => $customer->id, 'type' => 'velocity', 'severity' => 'high', 'context' => 'withdrawal',
        'score' => 60, 'status' => 'open', 'case_id' => $case->id,
    ]);
    ScreeningResult::create([
        'user_id' => $customer->id, 'context' => 'onboarding', 'provider' => 'stub', 'result' => 'clear', 'score' => 0,
    ]);
    RewardCampaign::create([
        'key' => 'cashback', 'name' => 'Card cashback', 'type' => 'percentage', 'rate_bps' => 50, 'is_active' => true,
    ]);
    DepositMethod::create([
        'asset_id' => Asset::where('currency_code', 'BDT')->value('id') ?? Asset::where('symbol', 'USDT')->value('id'),
        'name' => 'bKash', 'type' => 'mobile', 'details' => ['number' => '01700-000000'], 'min_amount' => '10000', 'is_active' => true, 'sort' => 0,
    ]);
    RewardGrant::create([
        'user_id' => $customer->id, 'type' => 'cashback', 'asset_id' => Asset::where('symbol', 'USDT')->value('id'),
        'amount' => '125000', 'idempotency_key' => 'reward:smoke:1',
    ]);

    foreach (['dashboard', 'kyc', 'compliance', 'deposits', 'withdrawals', 'transfers', 'exchange', 'ledger', 'reports', 'revenue', 'treasury', 'users', 'assets', 'deposit-methods', 'withdrawal-methods', 'cards', 'card-disputes', 'card-providers', 'merchants', 'rewards', 'messaging', 'simulation', 'blockchain-health', 'rpc-endpoints', 'custody', 'pages', 'faqs', 'notifications', 'activity-logs', 'roles', 'administrators', 'settings'] as $route) {
        actingAs($admin, 'admin')->get(route("admin.{$route}"))->assertOk();
    }
});

it('redirects guests from the admin console to admin login', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

it('keeps consumer users out of the admin guard entirely', function () {
    $user = User::factory()->create();

    // A logged-in consumer is not on the admin guard, so the console still bounces them.
    actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});
