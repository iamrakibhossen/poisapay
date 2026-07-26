<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use RuntimeException;

/**
 * Thrown when the seller's available balance can't cover the gross escrow for an
 * order. Distinct from other order failures so {@see CreateOrderAction} can
 * auto-pause the ad when the short party is the ad owner.
 */
class InsufficientEscrowFundsException extends RuntimeException {}
