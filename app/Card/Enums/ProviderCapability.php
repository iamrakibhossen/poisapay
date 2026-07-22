<?php

declare(strict_types=1);

namespace App\Card\Enums;

/** Features a provider may or may not support; declared via capabilities(). */
enum ProviderCapability: string
{
    case VirtualCards = 'virtual_cards';
    case PhysicalCards = 'physical_cards';
    case Freeze = 'freeze';
    case Terminate = 'terminate';
    case Replace = 'replace';
    case SpendControls = 'spend_controls';
    case RevealPan = 'reveal_pan';
    case SetPin = 'set_pin';
    case JitFunding = 'jit_funding';
    case Webhooks = 'webhooks';
    case SyncBalance = 'sync_balance';
    case SyncTransactions = 'sync_transactions';
    case Refund = 'refund';
    case Reverse = 'reverse';
}
