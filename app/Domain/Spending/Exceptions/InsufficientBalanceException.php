<?php

declare(strict_types=1);

namespace App\Domain\Spending\Exceptions;

use RuntimeException;

/**
 * The user's balances — across every priority asset, including auto-convertible
 * ones — cannot cover the requested amount. A business failure (extends
 * RuntimeException) so redirect+flash money-path controllers surface the message.
 */
class InsufficientBalanceException extends RuntimeException {}
