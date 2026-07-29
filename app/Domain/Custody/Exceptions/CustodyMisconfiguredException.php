<?php

declare(strict_types=1);

namespace App\Domain\Custody\Exceptions;

use RuntimeException;

/** The custody-mode flags disagree (env/config vs settings). Fail fast — never guess. */
class CustodyMisconfiguredException extends RuntimeException {}
