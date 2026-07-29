<?php

declare(strict_types=1);

namespace App\Domain\Custody\Exceptions;

use RuntimeException;

/** A required signer, hot/gas wallet, or RPC is unavailable — refuse to broadcast. */
class CustodyNotReadyException extends RuntimeException {}
