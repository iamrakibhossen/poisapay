<?php

declare(strict_types=1);

namespace App\Shop\Models;

use App\Shop\Enums\DnsRecordType;
use App\Shop\Enums\DomainSslStatus;
use App\Shop\Enums\DomainStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A custom domain mapped to a single sales page. Ownership is verified via DNS
 * and SSL is provisioned automatically; the routing layer serves the page when
 * the domain is {@see self::isServiceable()}.
 *
 * @property DomainStatus $status
 * @property DomainSslStatus $ssl_status
 * @property ?DnsRecordType $dns_record_type
 */
class Domain extends Model
{
    use HasUuids;

    protected $table = 'shop_domains';

    protected $fillable = [
        'seller_id', 'sales_page_id', 'host', 'status', 'ssl_status',
        'dns_record_type', 'verification_token', 'verify_attempts',
        'ssl_attempts', 'last_error', 'last_checked_at', 'verified_at', 'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'ssl_status' => DomainSslStatus::class,
            'dns_record_type' => DnsRecordType::class,
            'verify_attempts' => 'integer',
            'ssl_attempts' => 'integer',
            'last_checked_at' => 'datetime',
            'verified_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return BelongsTo<SalesPage, $this> */
    public function salesPage(): BelongsTo
    {
        return $this->belongsTo(SalesPage::class);
    }

    public function isVerified(): bool
    {
        return $this->status === DomainStatus::Verified;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function isSslActive(): bool
    {
        return $this->ssl_status === DomainSslStatus::Active;
    }

    /** Verified, enabled — the routing layer may serve this domain's page. */
    public function isServiceable(): bool
    {
        return $this->isVerified() && ! $this->isDisabled();
    }

    /** The apex form of the host (leading `www.` stripped) — www is served as an alias. */
    public function apexHost(): string
    {
        return str_starts_with($this->host, 'www.') ? substr($this->host, 4) : $this->host;
    }

    /** @param Builder<Domain> $query */
    public function scopeServiceable(Builder $query): void
    {
        $query->where('status', DomainStatus::Verified->value)->whereNull('disabled_at');
    }
}
