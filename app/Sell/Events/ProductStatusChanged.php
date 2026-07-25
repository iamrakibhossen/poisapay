<?php

declare(strict_types=1);

namespace App\Sell\Events;

use App\Sell\Enums\ProductStatus;
use App\Sell\Models\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * A product was published or archived. Audited per status
 * (`sell.product.published` / `.archived`); a listener warms/invalidates the
 * public sales-page cache on publish.
 */
class ProductStatusChanged extends SellDomainEvent
{
    public function __construct(
        public readonly Product $product,
        public readonly ProductStatus $from,
        public readonly ProductStatus $to,
    ) {}

    public function auditAction(): string
    {
        return 'product.'.$this->to->value;
    }

    public function auditSubject(): ?Model
    {
        return $this->product;
    }

    public function auditData(): array
    {
        return ['from' => $this->from->value, 'to' => $this->to->value];
    }
}
