<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CardProvider;
use Illuminate\Database\Seeder;

class CardProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'PoisaPay Demo Issuer', 'slug' => 'poisapay-demo', 'network' => 'visa',
                'bin' => '453201', 'supports_virtual' => true, 'supports_physical' => true,
                'settlement_currency' => 'USD', 'api_base' => 'https://sandbox.poisapay.test/issuer',
                'is_demo' => true, 'is_active' => true, 'sort' => 1,
            ],
            [
                'name' => 'Visa Sandbox (Demo)', 'slug' => 'visa-sandbox', 'network' => 'visa',
                'bin' => '400000', 'supports_virtual' => true, 'supports_physical' => false,
                'settlement_currency' => 'USD', 'api_base' => 'https://sandbox.visa.test',
                'is_demo' => true, 'is_active' => true, 'sort' => 2,
            ],
            [
                'name' => 'Mastercard Sandbox (Demo)', 'slug' => 'mc-sandbox', 'network' => 'mastercard',
                'bin' => '520000', 'supports_virtual' => true, 'supports_physical' => true,
                'settlement_currency' => 'USD', 'api_base' => 'https://sandbox.mastercard.test',
                'is_demo' => true, 'is_active' => true, 'sort' => 3,
            ],
        ];

        foreach ($providers as $p) {
            CardProvider::updateOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
