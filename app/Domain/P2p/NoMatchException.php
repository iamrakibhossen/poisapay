<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use RuntimeException;

/** No eligible ad could be matched for an auto-match request. */
class NoMatchException extends RuntimeException {}
