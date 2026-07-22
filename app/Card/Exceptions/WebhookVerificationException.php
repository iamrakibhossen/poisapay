<?php

declare(strict_types=1);

namespace App\Card\Exceptions;

/** Inbound webhook / JIT request failed signature or auth verification. */
class WebhookVerificationException extends CardProviderException
{
}
