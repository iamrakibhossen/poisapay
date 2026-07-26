<?php

declare(strict_types=1);

namespace App\Shop\Exceptions;

/**
 * Domain-connection errors — thrown by the custom-domain Actions with a clear,
 * merchant-facing message (rendered as 422 over HTTP via {@see ShopException}).
 */
class DomainException extends ShopException
{
    public static function disabled(): self
    {
        return new self('Custom domains are not available right now.');
    }

    public static function invalidFormat(string $input): self
    {
        return new self("“{$input}” is not a valid domain. Enter a domain like shop.yourbrand.com.");
    }

    public static function reserved(string $host): self
    {
        return new self("The domain “{$host}” is reserved and cannot be connected.");
    }

    public static function platformDomain(string $host): self
    {
        return new self("“{$host}” belongs to the platform and cannot be connected.");
    }

    public static function duplicate(string $host): self
    {
        return new self("The domain “{$host}” is already connected to another shop.");
    }

    public static function pageAlreadyHasDomain(): self
    {
        return new self('This sales page already has a custom domain. Remove it first.');
    }

    public static function notOwned(): self
    {
        return new self('You do not have permission to manage this domain.');
    }
}
