<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Exceptions;

use RuntimeException;

/** The destination address does not match the asset's network (TRON `T…` / EVM `0x…`). */
class InvalidWithdrawalAddressException extends RuntimeException {}
