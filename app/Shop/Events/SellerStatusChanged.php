<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Models\Admin;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Seller;
use Illuminate\Database\Eloquent\Model;

/**
 * A seller's status changed (approved / rejected / suspended / reinstated).
 * Audited per target status (`sell.seller.approved`, …). Listeners branch on
 * `$to` — e.g. send a welcome + provision on approval.
 */
class SellerStatusChanged extends ShopDomainEvent
{
    public function __construct(
        public readonly Seller $seller,
        public readonly SellerStatus $from,
        public readonly SellerStatus $to,
        public readonly ?Admin $operator = null,
        public readonly ?string $reason = null,
    ) {}

    public function auditAction(): string
    {
        return 'seller.'.$this->to->value;
    }

    public function auditSubject(): ?Model
    {
        return $this->seller;
    }

    public function auditData(): array
    {
        return ['from' => $this->from->value, 'to' => $this->to->value, 'reason' => $this->reason];
    }

    public function auditActor(): ?Model
    {
        return $this->operator;
    }
}
