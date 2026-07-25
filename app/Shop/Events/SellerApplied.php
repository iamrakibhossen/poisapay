<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Models\User;
use App\Shop\Models\Seller;
use Illuminate\Database\Eloquent\Model;

class SellerApplied extends ShopDomainEvent
{
    public function __construct(
        public readonly Seller $seller,
        public readonly User $user,
    ) {}

    public function auditAction(): string
    {
        return 'seller.applied';
    }

    public function auditSubject(): ?Model
    {
        return $this->seller;
    }

    public function auditActor(): ?Model
    {
        return $this->user;
    }
}
