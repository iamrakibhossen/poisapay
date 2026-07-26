<?php

declare(strict_types=1);

namespace App\Shop\Actions\Domain;

use App\Shop\Enums\DomainSslStatus;
use App\Shop\Enums\DomainStatus;
use App\Shop\Events\DomainCreated;
use App\Shop\Exceptions\DomainException;
use App\Shop\Jobs\VerifyDomainJob;
use App\Shop\Models\Domain;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Services\Domain\DomainValidator;
use App\Shop\Support\DomainName;
use Illuminate\Support\Str;

/**
 * Connect a custom domain to one of the seller's sales pages. Normalizes and
 * validates the host, mints an ownership token, persists a Pending domain, then
 * fires {@see DomainCreated} and queues the first verification pass.
 */
class ConnectDomain
{
    public function __construct(private readonly DomainValidator $validator) {}

    public function execute(Seller $seller, SalesPage $page, string $rawHost): Domain
    {
        if (! feature('shop_custom_domains', false)) {
            throw DomainException::disabled();
        }

        if ($page->seller_id !== $seller->getKey()) {
            throw DomainException::notOwned();
        }

        if ($page->domain()->exists()) {
            throw DomainException::pageAlreadyHasDomain();
        }

        $host = DomainName::normalize($rawHost);
        $this->validator->validate($host);

        $domain = Domain::create([
            'seller_id' => $seller->getKey(),
            'sales_page_id' => $page->getKey(),
            'host' => $host,
            'status' => DomainStatus::Pending,
            'ssl_status' => DomainSslStatus::Pending,
            'verification_token' => Str::lower(Str::random(40)),
        ]);

        DomainCreated::dispatch($domain);
        VerifyDomainJob::dispatch($domain->getKey());

        return $domain;
    }
}
