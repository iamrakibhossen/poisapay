<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\DepositMethod;
use Illuminate\Database\Seeder;

/** Default deposit methods (§6.1). Fiat funds via bank/mobile; crypto via chain. */
class DepositMethodSeeder extends Seeder
{
    public function run(): void
    {
        $bdt = Asset::where('currency_code', 'BDT')->first();
        $usd = Asset::where('currency_code', 'USD')->first();

        if ($bdt) {
            $this->method($bdt->id, 'bKash', 'mobile', 0, [
                'provider' => 'bKash', 'account_type' => 'Merchant', 'number' => '01700-000000',
            ], 'Send money to the bKash number above, then enter your bKash Transaction ID as the reference.', '10000', '5000000');

            $this->method($bdt->id, 'Nagad', 'mobile', 1, [
                'provider' => 'Nagad', 'account_type' => 'Merchant', 'number' => '01800-000000',
            ], 'Send money via Nagad, then enter the Transaction ID as the reference.', '10000', '5000000');

            $this->method($bdt->id, 'City Bank', 'bank', 2, [
                'account_holder' => 'PoisaPay Ltd', 'bank_name' => 'City Bank', 'account_number' => '1234567890', 'branch' => 'Gulshan', 'routing_number' => '225261729',
            ], 'Transfer to the account above and enter the bank reference number.', '100000', null);
        }

        if ($usd) {
            $this->method($usd->id, 'Wire transfer', 'bank', 0, [
                'account_holder' => 'PoisaPay Ltd', 'bank_name' => 'Standard Chartered', 'account_number' => 'US00-1234-5678', 'swift' => 'SCBLUS33',
            ], 'Send an international wire and enter the wire reference.', '500', null);
        }
    }

    private function method(int $assetId, string $name, string $type, int $sort, array $details, string $instructions, string $min, ?string $max): void
    {
        DepositMethod::firstOrCreate(
            ['asset_id' => $assetId, 'name' => $name],
            [
                'type' => $type,
                'details' => $details,
                'instructions' => $instructions,
                'min_amount' => $min,
                'max_amount' => $max,
                'is_active' => true,
                'sort' => $sort,
            ],
        );
    }
}
