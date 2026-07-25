<?php

declare(strict_types=1);

namespace App\Sell\Events;

use App\Sell\Models\Product;
use Illuminate\Database\Eloquent\Model;

/** A product was created or updated. `$created` distinguishes; audited either way. */
class ProductSaved extends SellDomainEvent
{
    public function __construct(
        public readonly Product $product,
        public readonly bool $created,
    ) {}

    public function auditAction(): string
    {
        return $this->created ? 'product.created' : 'product.updated';
    }

    public function auditSubject(): ?Model
    {
        return $this->product;
    }

    public function auditData(): array
    {
        return ['type' => $this->product->type->value, 'name' => $this->product->name];
    }
}
