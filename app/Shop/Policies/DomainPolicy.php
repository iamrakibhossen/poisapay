<?php

declare(strict_types=1);

namespace App\Shop\Policies;

use App\Models\Admin;
use App\Shop\Models\Domain;
use Illuminate\Contracts\Auth\Authenticatable;

/** A seller may only manage their own domains; operators may view/manage any. */
class DomainPolicy
{
    public function view(Authenticatable $actor, Domain $domain): bool
    {
        return $this->owns($actor, $domain) || $actor instanceof Admin;
    }

    public function update(Authenticatable $actor, Domain $domain): bool
    {
        return $this->owns($actor, $domain);
    }

    public function delete(Authenticatable $actor, Domain $domain): bool
    {
        return $this->owns($actor, $domain);
    }

    private function owns(Authenticatable $actor, Domain $domain): bool
    {
        return ! $actor instanceof Admin && $domain->seller->user_id === $actor->getAuthIdentifier();
    }
}
