<?php

declare(strict_types=1);

namespace App\Card\Exceptions;

use RuntimeException;

/** Base for every fault raised inside the card provider layer. */
class CardProviderException extends RuntimeException
{
}
