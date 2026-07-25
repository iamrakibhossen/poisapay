<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * Ledger account classification (TDD §5.1). Determines normal balance side and
 * whether the account belongs to a user or the platform (system) chart.
 */
enum LedgerAccountType: string
{
    use HasMeta;

    // User-owned balance buckets
    case UserAvailable = 'user:available';
    case UserLocked = 'user:locked';
    case UserCardHold = 'user:card_hold';
    case UserCollateralLocked = 'user:collateral:locked';
    case UserP2pEscrow = 'user:p2p_escrow';   // seller USDT held during a P2P trade

    // Treasury / system accounts
    case TreasuryHot = 'treasury:hot';
    case TreasuryCold = 'treasury:cold';
    case TreasuryPending = 'treasury:pending';
    case TreasuryOut = 'treasury:out';

    // House dealer inventory (NOT custody). One per asset. This is the exchange's
    // own currency position from acting as principal on internal swaps/conversions.
    // Invariant per asset:  TreasuryHot(+Cold+Pending) = CustomerLiabilities + TradingInventory + Income.
    // Custody accounts move ONLY on real chain/bank movement; TradingInventory
    // moves ONLY on internal conversions — so custody always reconciles to chain.
    case TradingInventory = 'dealer:inventory';

    case LiabilityUserFunds = 'liability:user-funds';
    case FeeIncome = 'fee:income';
    case FeeCard = 'fee:card';
    case GasExpense = 'gas:expense';
    case FxSpreadIncome = 'fx:spread_income';
    case P2pFeeIncome = 'p2p:fee_income';   // platform taker fee on P2P trades
    case SellCommissionIncome = 'sell:commission_income';   // marketplace commission on Sell orders
    case RampClearing = 'ramp:clearing';
    case CardProgramSettlement = 'card_program:settlement';
    case CardProgramLoss = 'card_program:loss';
    case Rewards = 'rewards:pool';
    case CreditPrincipal = 'credit:principal';
    case CreditAccruedFee = 'credit:accrued_fee';
    case OwnerPayout = 'owner:payout';     // cumulative profit withdrawn by the operator

    public function isUserAccount(): bool
    {
        return str_starts_with($this->value, 'user:');
    }

    /**
     * Normal balance side. Asset & expense accounts are debit-normal;
     * liability, income & user-fund accounts are credit-normal.
     */
    public function normalSide(): LedgerSide
    {
        return match ($this) {
            self::UserAvailable, self::UserLocked, self::UserCardHold,
            self::UserCollateralLocked, self::UserP2pEscrow, self::LiabilityUserFunds,
            self::TradingInventory,
            self::FeeIncome, self::FeeCard, self::FxSpreadIncome, self::P2pFeeIncome,
            self::SellCommissionIncome,
            self::CardProgramSettlement, self::Rewards, self::OwnerPayout => LedgerSide::Credit,
            default => LedgerSide::Debit,
        };
    }

    public function label(): string
    {
        return str($this->value)->replace(['user:', 'treasury:', ':'], ['', '', ' '])->headline()->toString();
    }
}
