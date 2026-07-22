<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\WithdrawalMethod;
use Illuminate\Database\Seeder;

/** Default fiat payout rails (§6.3). Operators can edit these per currency. */
class WithdrawalMethodSeeder extends Seeder
{
    public function run(): void
    {
        $bdt = Asset::where('currency_code', 'BDT')->first();
        $usd = Asset::where('currency_code', 'USD')->first();

        if ($bdt) {
            $this->method($bdt->id, 'bKash', 'mobile', 0, ['number_label' => 'bKash number'], '5000', '2500000');
            $this->method($bdt->id, 'Nagad', 'mobile', 1, ['number_label' => 'Nagad number'], '5000', '2500000');
            $this->method($bdt->id, 'Rocket', 'mobile', 2, ['number_label' => 'Rocket number'], '5000', '2500000');
            $this->method($bdt->id, 'Bank transfer', 'bank', 3, ['number_label' => 'Account number'], '100000', null);
        }

        if ($usd) {
            $this->method($usd->id, 'Bank wire', 'bank', 0, ['number_label' => 'Account / IBAN'], '5000', null);
        }
    }

    private function method(int $assetId, string $name, string $type, int $sort, array $details, string $min, ?string $max): void
    {
        WithdrawalMethod::firstOrCreate(
            ['asset_id' => $assetId, 'name' => $name],
            [
                'type' => $type,
                'details' => $details,
                'min_amount' => $min,
                'max_amount' => $max,
                'is_active' => true,
                'sort' => $sort,
            ],
        );
    }
}
