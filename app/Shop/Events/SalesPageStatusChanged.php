<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Shop\Enums\SalesPageStatus;
use App\Shop\Models\SalesPage;
use Illuminate\Database\Eloquent\Model;

/**
 * A sales page was published or archived. Audited (`sell.sales_page.published`);
 * a listener can warm the public cache on publish so the first ad click is a hit.
 */
class SalesPageStatusChanged extends ShopDomainEvent
{
    public function __construct(
        public readonly SalesPage $page,
        public readonly SalesPageStatus $from,
        public readonly SalesPageStatus $to,
    ) {}

    public function auditAction(): string
    {
        return 'sales_page.'.$this->to->value;
    }

    public function auditSubject(): ?Model
    {
        return $this->page;
    }

    public function auditData(): array
    {
        return ['slug' => $this->page->slug, 'from' => $this->from->value, 'to' => $this->to->value];
    }
}
