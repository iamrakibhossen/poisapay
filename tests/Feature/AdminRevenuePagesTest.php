<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Revenue\RequestRevenueWithdrawalAction;
use App\Domain\Revenue\RevenueService;
use App\Enums\LedgerAccountType;
use App\Enums\RevenueWithdrawalStatus;
use App\Jobs\BroadcastRevenueWithdrawalJob;
use App\Models\Admin;
use App\Models\Asset;
use App\Models\ProfitPayout;
use App\Models\RevenueWithdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Roles live on the admin guard; super-admin carries every revenue permission.
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->resolver = app(AccountResolver::class);
    $this->revenue = app(RevenueService::class);

    $this->operator = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->operator->syncRoles(['super-admin']);
});

/** Post a balanced fee credit so the revenue/profit accounts have a balance. */
function seedFee($ledger, $resolver, $asset, string $base, LedgerAccountType $type = LedgerAccountType::FeeIncome): void
{
    $pending = $resolver->system(LedgerAccountType::TreasuryPending, $asset->id);
    $fee = $resolver->system($type, $asset->id);
    $ledger->post(new EntryData(
        type: 'test.fee',
        idempotencyKey: 'test:rev:'.uniqid('', true),
        lines: [PostingLine::debit($pending->id, $asset->id, $base), PostingLine::credit($fee->id, $asset->id, $base)],
    ));
}

it('loads the unified revenue page and redirects the old finance pages', function () {
    seedFee($this->ledger, $this->resolver, $this->usdt, '1000000');

    // The merged Revenue page renders everything.
    actingAs($this->operator, 'admin')->get(route('admin.revenue'))->assertOk();

    // The old standalone pages now redirect into it.
    foreach (['revenue-wallet', 'revenue-withdrawals', 'revenue-transactions'] as $route) {
        actingAs($this->operator, 'admin')->get(route("admin.{$route}"))->assertRedirect(route('admin.revenue'));
    }
});

it('records a profit payout and posts the ledger', function () {
    seedFee($this->ledger, $this->resolver, $this->usdt, '1000000');

    $response = actingAs($this->operator, 'admin')->post(route('admin.revenue.withdraw'), [
        'asset_id' => $this->usdt->id,
        'amount' => '0.4',
        'destination' => 'Company bank',
        'note' => 'monthly',
    ]);

    $response->assertRedirect()->assertSessionHas('success');

    $payout = ProfitPayout::where('asset_id', $this->usdt->id)->latest()->first();
    expect($payout)->not->toBeNull()
        ->and($payout->amount)->toBe('400000')
        ->and($payout->entry_id)->not->toBeNull();
});

it('rejects the on-chain approval flow for fiat revenue', function () {
    $bdt = Asset::firstOrCreate(
        ['symbol' => 'BDT', 'chain_id' => null, 'contract_address' => null],
        ['name' => 'Taka', 'kind' => 'fiat', 'currency_code' => 'BDT', 'decimals' => 2, 'is_active' => true],
    );
    $this->resolver->ensureSystemAccounts($bdt->id);
    seedFee($this->ledger, $this->resolver, $bdt, '100000'); // 1,000.00 BDT revenue

    actingAs($this->operator, 'admin')->post(route('admin.revenue-wallet.withdraw'), [
        'asset_id' => $bdt->id,
        'amount' => '5',
        'destination' => 'Company bank',
        'password' => 'password',
    ])->assertSessionHasErrors('amount');

    expect(RevenueWithdrawal::where('asset_id', $bdt->id)->exists())->toBeFalse();
});

it('requests a revenue withdrawal from the merged page (asset_id, no explicit network)', function () {
    seedFee($this->ledger, $this->resolver, $this->usdt, '1000000');

    // The merged Revenue page sends asset_id and omits network (derived from the chain).
    actingAs($this->operator, 'admin')->post(route('admin.revenue-wallet.withdraw'), [
        'asset_id' => $this->usdt->id,
        'amount' => '0.3',
        'destination' => 'TdestExchange',
        'password' => 'password',
    ])->assertRedirect()->assertSessionHas('success');

    $w = RevenueWithdrawal::latest()->first();
    expect($w->network)->toBe($this->usdt->chain->name)
        ->and($w->status)->toBe(RevenueWithdrawalStatus::Pending);
});

it('requests a revenue withdrawal without moving money', function () {
    seedFee($this->ledger, $this->resolver, $this->usdt, '1000000');

    $response = actingAs($this->operator, 'admin')->post(route('admin.revenue-wallet.withdraw'), [
        'amount' => '0.4',
        'network' => 'tron',
        'destination' => '0xabc',
        'password' => 'password',
    ]);

    $response->assertRedirect()->assertSessionHas('success');

    $w = RevenueWithdrawal::latest()->first();
    expect($w)->not->toBeNull()
        ->and($w->status)->toBe(RevenueWithdrawalStatus::Pending)
        ->and($this->revenue->balance($this->usdt)->baseString())->toBe('1000000');
});

it('rejects a revenue withdrawal with the wrong password', function () {
    seedFee($this->ledger, $this->resolver, $this->usdt, '1000000');

    $response = actingAs($this->operator, 'admin')->from(route('admin.revenue-wallet'))
        ->post(route('admin.revenue-wallet.withdraw'), [
            'amount' => '0.4',
            'network' => 'tron',
            'destination' => '0xabc',
            'password' => 'wrong-password',
        ]);

    $response->assertRedirect(route('admin.revenue-wallet'))->assertSessionHasErrors('password');
    expect(RevenueWithdrawal::count())->toBe(0);
});

it('approves a pending revenue withdrawal and moves the ledger', function () {
    Queue::fake();
    seedFee($this->ledger, $this->resolver, $this->usdt, '1000000');
    $w = app(RequestRevenueWithdrawalAction::class)->execute(
        $this->operator, $this->usdt, Money::ofBase('400000', 6, 'USDT'), 'tron', '0xabc'
    );

    $response = actingAs($this->operator, 'admin')->post(route('admin.revenue-withdrawals.approve', $w->id), [
        'password' => 'password',
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    expect($w->fresh()->status)->toBe(RevenueWithdrawalStatus::Approved)
        ->and($w->fresh()->entry_id)->not->toBeNull()
        ->and($this->revenue->balance($this->usdt)->baseString())->toBe('600000');
    Queue::assertPushed(BroadcastRevenueWithdrawalJob::class);
});

it('exports revenue transactions as CSV', function () {
    seedFee($this->ledger, $this->resolver, $this->usdt, '1000000');

    $response = actingAs($this->operator, 'admin')->get(route('admin.revenue-transactions.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $response->assertDownload();
});
