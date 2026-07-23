<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A Travel Rule (FATF R.16) originator/beneficiary record (Wave 5).
 *
 * @property string $id
 * @property string|null $withdrawal_id
 * @property int|null $asset_id
 * @property string $direction
 * @property string $amount
 * @property string $status
 */
class TravelRuleRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'withdrawal_id', 'asset_id', 'direction', 'amount',
        'originator_name', 'originator_account',
        'beneficiary_name', 'beneficiary_vasp', 'beneficiary_address',
        'status', 'provider', 'provider_ref',
    ];
}
