<?php

declare(strict_types=1);

namespace App\Sell\Events;

use App\Models\User;
use App\Sell\Models\Seller;
use Illuminate\Database\Eloquent\Model;

class SellerApplied extends SellDomainEvent
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
