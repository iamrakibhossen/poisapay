<?php

declare(strict_types=1);

namespace App\Shop\Exceptions;

use App\Shop\Enums\OrderStatus;
use App\Shop\Enums\SellerStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Domain error for the Sell module — thrown by Actions, rendered as 422 over HTTP. */
class ShopException extends RuntimeException
{
    public function render(Request $request): ?JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $this->getMessage()], 422)
            : null;
    }

    public static function disabled(): self
    {
        return new self('The seller platform is not available right now.');
    }

    public static function alreadyApplied(SellerStatus $status): self
    {
        return new self("You already have a seller account ({$status->label()}).");
    }

    public static function invalidSellerTransition(SellerStatus $from, SellerStatus $to): self
    {
        return new self("Cannot move a seller from {$from->label()} to {$to->label()}.");
    }

    public static function invalidOrderTransition(OrderStatus $from, OrderStatus $to): self
    {
        return new self("Cannot move an order from {$from->label()} to {$to->label()}.");
    }

    public static function notApproved(): self
    {
        return new self('Your seller account is not approved to publish products.');
    }

    public static function notPublishable(string $why): self
    {
        return new self("This product can't be published: {$why}.");
    }

    public static function notBuyable(): self
    {
        return new self('This product is not available for purchase.');
    }

    public static function cannotBuyOwn(): self
    {
        return new self('You cannot buy your own product.');
    }

    public static function insufficientBalance(): self
    {
        return new self('Insufficient PoisaPay balance for this purchase.');
    }

    public static function outOfStock(): self
    {
        return new self('This item is out of stock.');
    }

    public static function invalidCoupon(string $why = 'This coupon code is not valid.'): self
    {
        return new self($why);
    }

    public static function notRefundable(): self
    {
        return new self('This order can no longer be refunded.');
    }

    public static function refundRequestOpen(): self
    {
        return new self('There is already an open refund request for this order.');
    }

    public static function invalidRefundAmount(): self
    {
        return new self('The refund amount is more than what remains on this order.');
    }

    public static function refundRequestClosed(): self
    {
        return new self('This refund request has already been resolved.');
    }
}
