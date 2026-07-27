<?php

declare(strict_types=1);

namespace App\Domain\Spending\Exceptions;

use RuntimeException;

/**
 * The platform lacks the settlement-asset liquidity (dealer inventory) to fill
 * the auto-conversions a spend needs. Never approve a spend that cannot settle.
 */
class InsufficientLiquidityException extends RuntimeException {}
