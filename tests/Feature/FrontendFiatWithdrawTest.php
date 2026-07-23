<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Domain\Withdrawal\SettleWithdrawalAction;
use App\Enums\KycTier;
use App\Enums\LedgerAccountType;
use App\Enums\WithdrawalStatus;
use App\Models\Asset;
use App\Models\OnchainTx;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->bdt = Asset::firstOrCreate(
        ['symbol' => 'BDT', 'chain_id' => null, 'contract_address' => null],
        ['name' => 'Bangladeshi Taka', 'kind' => 'fiat', 'currency_code' => 'BDT', 'decimals' => 2,
            'is_active' => true, 'withdrawal_min' => '0', 'withdrawal_fee' => '0'],
    );
    app(AccountResolver::class)->ensureSystemAccounts($this->bdt->id);
    $this->ledger = app(LedgerService::class);

    // Per-currency payout rails (operator-configured).
    $this->bkash = WithdrawalMethod::create([
        'asset_id' => $this->bdt->id, 'name' => 'bKash', 'type' => 'mobile',
        'details' => ['number_label' => 'bKash number'], 'min_amount' => '5000', 'max_amount' => '2500000',
        'is_active' => true, 'sort' => 0,
    ]);
    $this->bank = WithdrawalMethod::create([
        'asset_id' => $this->bdt->id, 'name' => 'Bank transfer', 'type' => 'bank',
        'min_amount' => '100000', 'is_active' => true, 'sort' => 1,
    ]);

    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->user->forceFill(['created_at' => now()->subMonth()])->save();
    creditUser($this->user, $this->bdt, '500000'); // 5,000.00 BDT
});

it('offers a fiat cash-out option on the withdraw page', function () {
    actingAs($this->user)->get(route('withdraw'))
        ->assertOk()->assertSee('Local Withdraw')->assertSee('BDT cash-out');
});

it('settles a fiat cash-out without an on-chain tx and books the fee to revenue', function () {
    $resolver = app(AccountResolver::class);
    $request = app(RequestWithdrawalAction::class);
    $settle = app(SettleWithdrawalAction::class);

    // Request a fiat withdrawal with a 10.00 BDT rail fee.
    $withdrawal = $request->execute(
        $this->user, $this->bdt, $this->bdt->money('100000'), 'bKash •••7806',
        'fiat-fee-key', 'mobile', ['number' => '017...'], $this->bdt->money('1000'),
    );

    $feeAccount = $resolver->system(LedgerAccountType::FeeIncome, $this->bdt->id);
    $feeBefore = (int) (DB::table('account_balances')->where('account_id', $feeAccount->id)->value('balance') ?? '0');

    // Settle it (the simulated chain tick passes a tx hash) — must NOT crash on the null chain.
    $settle->execute($withdrawal->fresh(), '0x'.bin2hex(random_bytes(16)));

    $withdrawal->refresh();
    $feeAfter = (int) (DB::table('account_balances')->where('account_id', $feeAccount->id)->value('balance') ?? '0');

    expect($withdrawal->status)->toBe(WithdrawalStatus::Completed)
        ->and($withdrawal->onchain_tx_id)->toBeNull()                 // no on-chain tx for fiat
        ->and(OnchainTx::count())->toBe(0)
        ->and($withdrawal->fee)->toBe('1000')                          // 10.00 BDT rail fee (test env: 0% platform fee)
        ->and($feeAfter - $feeBefore)->toBe(1000);                     // fee reached revenue
});

it('renders the per-currency payout methods on the cash-out form', function () {
    actingAs($this->user)->get(route('withdraw', ['cash' => $this->bdt->id]))
        ->assertOk()
        ->assertSee('Payout method')
        ->assertSee('bKash')
        ->assertSee('Bank transfer');
});

it('requests a mobile cash-out against a configured method and reserves funds', function () {
    actingAs($this->user)->post(route('withdraw.fiat'), [
        'assetId' => $this->bdt->id, 'methodId' => $this->bkash->id,
        'accountName' => 'Rakib Hossen', 'accountNumber' => '01711000000', 'amount' => '1000',
    ])->assertRedirect(route('withdraw'))->assertSessionHas('success');

    $w = Withdrawal::where('user_id', $this->user->id)->firstOrFail();
    expect($w->payout_method)->toBe('mobile')
        ->and($w->payout_details['method'])->toBe('bKash')
        ->and($w->payout_details['account_number'])->toBe('01711000000')
        ->and($this->ledger->lockedBalance($this->user, $this->bdt->id)->baseString())->toBe('100000');
});

it('requires a bank name for a bank-type method', function () {
    actingAs($this->user)->post(route('withdraw.fiat'), [
        'assetId' => $this->bdt->id, 'methodId' => $this->bank->id,
        'accountName' => 'Rakib', 'accountNumber' => '1501203456789', 'amount' => '2000',
    ])->assertSessionHasErrors('bankName');

    expect(Withdrawal::where('user_id', $this->user->id)->count())->toBe(0);
});

it('enforces the method minimum', function () {
    actingAs($this->user)->post(route('withdraw.fiat'), [
        'assetId' => $this->bdt->id, 'methodId' => $this->bkash->id,
        'accountName' => 'Rakib', 'accountNumber' => '01711000000', 'amount' => '10', // below 50.00 min
    ])->assertSessionHasErrors('amount');
});

it('can save the account for reuse', function () {
    actingAs($this->user)->post(route('withdraw.fiat'), [
        'assetId' => $this->bdt->id, 'methodId' => $this->bkash->id,
        'accountName' => 'Rakib Hossen', 'accountNumber' => '01711000000', 'amount' => '1000',
        'saveAccount' => '1', 'label' => 'My bKash',
    ])->assertRedirect();

    $account = PayoutAccount::where('user_id', $this->user->id)->firstOrFail();
    expect($account->label)->toBe('My bKash')
        ->and($account->withdrawal_method_id)->toBe($this->bkash->id)
        ->and($account->account_number)->toBe('01711000000');
});

it('pays out to a saved account', function () {
    $account = PayoutAccount::create([
        'user_id' => $this->user->id, 'asset_id' => $this->bdt->id, 'withdrawal_method_id' => $this->bkash->id,
        'label' => 'My bKash', 'account_name' => 'Rakib', 'account_number' => '01722000000',
    ]);

    actingAs($this->user)->post(route('withdraw.fiat'), [
        'assetId' => $this->bdt->id, 'accountId' => $account->id, 'amount' => '1000',
    ])->assertRedirect()->assertSessionHas('success');

    $w = Withdrawal::where('user_id', $this->user->id)->firstOrFail();
    expect($w->payout_method)->toBe('mobile')
        ->and($w->payout_details['account_number'])->toBe('01722000000')
        ->and($account->fresh()->last_used_at)->not->toBeNull();
});

it('applies the method fee (fixed + percent) to the reserved amount', function () {
    $withFee = WithdrawalMethod::create([
        'asset_id' => $this->bdt->id, 'name' => 'Nagad', 'type' => 'mobile',
        'min_amount' => '5000', 'fixed_fee' => '2000', 'percent_fee_bps' => 100, 'is_active' => true, 'sort' => 2,
    ]); // 20.00 fixed + 1%

    actingAs($this->user)->post(route('withdraw.fiat'), [
        'assetId' => $this->bdt->id, 'methodId' => $withFee->id,
        'accountName' => 'Rakib', 'accountNumber' => '01799000000', 'amount' => '1000',
    ])->assertRedirect()->assertSessionHas('success');

    // amount 1,000.00 = 100000 base; fee = 2000 fixed + 1% of 100000 (=1000) = 3000; locked = 103000.
    $w = Withdrawal::where('user_id', $this->user->id)->firstOrFail();
    expect($w->fee)->toBe('3000')
        ->and($this->ledger->lockedBalance($this->user, $this->bdt->id)->baseString())->toBe('103000');
});

it('deletes a saved account scoped to the owner', function () {
    $account = PayoutAccount::create([
        'user_id' => $this->user->id, 'asset_id' => $this->bdt->id, 'withdrawal_method_id' => $this->bkash->id,
        'account_name' => 'Rakib', 'account_number' => '01733000000',
    ]);

    actingAs($this->user)->delete(route('withdraw.account.delete', $account->id))->assertRedirect();
    expect(PayoutAccount::whereKey($account->id)->exists())->toBeFalse();
});

it('rejects a crypto asset on the fiat endpoint', function () {
    $usdt = testAsset('USDT', 6, 'tron');

    actingAs($this->user)->post(route('withdraw.fiat'), [
        'assetId' => $usdt->id, 'methodId' => $this->bkash->id,
        'accountName' => 'X', 'accountNumber' => '017', 'amount' => '1',
    ])->assertSessionHasErrors('assetId');
});
