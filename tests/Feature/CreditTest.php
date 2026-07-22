<?php

declare(strict_types=1);

use App\Domain\Credit\CreditService;
use App\Domain\Credit\DrawCreditAction;
use App\Domain\Credit\LiquidateCreditLineAction;
use App\Domain\Credit\OpenCreditLineAction;
use App\Domain\Credit\RepayCreditAction;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\CreditStatus;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\CreditLine;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->eth = Asset::firstOrCreate(
        ['symbol' => 'ETH', 'chain_id' => $this->usdt->chain_id, 'contract_address' => 'ETH_T'],
        ['name' => 'Ether', 'kind' => 'crypto', 'decimals' => 18],
    );
    app(AccountResolver::class)->ensureSystemAccounts($this->eth->id);

    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();

    // Give the user 1 ETH of collateral.
    creditEth($this->user, $this->eth, '1000000000000000000');
});

function creditEth(User $user, Asset $eth, string $base): void
{
    $ledger = app(LedgerService::class);
    $r = $ledger->resolver();
    $t = $r->system(LedgerAccountType::TreasuryPending, $eth->id);
    $a = $r->forUser($user, LedgerAccountType::UserAvailable, $eth->id);
    $ledger->post(new EntryData('seed', 'seed:eth:'.$user->id, [
        PostingLine::debit($t->id, $eth->id, $base),
        PostingLine::credit($a->id, $eth->id, $base),
    ]));
}

it('locks collateral when opening a credit line', function () {
    app(OpenCreditLineAction::class)->execute($this->user, $this->eth, Money::ofBase('1000000000000000000', 18, 'ETH'), $this->usdt);

    expect($this->ledger->availableBalance($this->user, $this->eth->id)->baseString())->toBe('0');
    expect(CreditLine::where('user_id', $this->user->id)->count())->toBe(1);
});

it('draws principal and reflects LTV', function () {
    $line = app(OpenCreditLineAction::class)->execute($this->user, $this->eth, Money::ofBase('1000000000000000000', 18, 'ETH'), $this->usdt);

    app(DrawCreditAction::class)->execute($line, Money::ofBase('1000000000', 6, 'USDT')); // 1000 USDT

    expect($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('1000000000')
        ->and(app(CreditService::class)->currentLtvBps($line->fresh()))->toBeGreaterThan(3000)
        ->and(app(CreditService::class)->currentLtvBps($line->fresh()))->toBeLessThan(3300);
});

it('rejects a draw beyond the max LTV', function () {
    $line = app(OpenCreditLineAction::class)->execute($this->user, $this->eth, Money::ofBase('1000000000000000000', 18, 'ETH'), $this->usdt);

    // 1 ETH ($3200) at 60% max => ~$1920 drawable. $5000 exceeds it.
    app(DrawCreditAction::class)->execute($line, Money::ofBase('5000000000', 6, 'USDT'));
})->throws(RuntimeException::class);

it('releases collateral and closes the line on full repayment', function () {
    $line = app(OpenCreditLineAction::class)->execute($this->user, $this->eth, Money::ofBase('1000000000000000000', 18, 'ETH'), $this->usdt);
    app(DrawCreditAction::class)->execute($line, Money::ofBase('1000000000', 6, 'USDT'));

    app(RepayCreditAction::class)->execute($line->fresh(), Money::ofBase('1000000000', 6, 'USDT'));

    $line->refresh();
    expect($line->status)->toBe(CreditStatus::Repaid)
        ->and($this->ledger->availableBalance($this->user, $this->eth->id)->baseString())->toBe('1000000000000000000')
        ->and($line->principal_drawn)->toBe('0');
});

it('liquidates a line that breaches the maintenance LTV', function () {
    // Fund treasury USDT so the collateral swap can deliver the principal asset.
    $r = app(AccountResolver::class);
    $hot = $r->system(LedgerAccountType::TreasuryHot, $this->usdt->id);
    $liab = $r->system(LedgerAccountType::LiabilityUserFunds, $this->usdt->id);
    $this->ledger->post(new EntryData('seed', 'seed:usdt:treasury', [
        PostingLine::debit($hot->id, $this->usdt->id, '100000000000'),
        PostingLine::credit($liab->id, $this->usdt->id, '100000000000'),
    ]));

    $line = app(OpenCreditLineAction::class)->execute($this->user, $this->eth, Money::ofBase('1000000000000000000', 18, 'ETH'), $this->usdt);
    app(DrawCreditAction::class)->execute($line, Money::ofBase('1000000000', 6, 'USDT'));

    // Force a breach by tightening the maintenance threshold below current LTV.
    $line->fresh()->update(['liquidation_ltv_bps' => 100]);

    app(LiquidateCreditLineAction::class)->execute($line->fresh());

    $line->refresh();
    expect($line->status->in([CreditStatus::Repaid, CreditStatus::Defaulted]))->toBeTrue()
        ->and($line->collateral_amount)->toBe('0');
});
