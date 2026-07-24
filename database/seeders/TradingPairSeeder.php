<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\TradingPair;
use Illuminate\Database\Seeder;

/** Seed the default supported trading pairs (stablecoin-centric). */
class TradingPairSeeder extends Seeder
{
    public function run(): void
    {
        $usdt = Asset::where('symbol', 'USDT')->first();
        $bdt = Asset::where('symbol', 'BDT')->first();
        $usd = Asset::where('symbol', 'USD')->first();
        $eur = Asset::where('symbol', 'EUR')->first();
        // Every tradable coin (crypto + non-hub stablecoins) pairs against the hubs.
        $cryptos = Asset::whereIn('symbol', ['ETH', 'BNB', 'TRX', 'BTC', 'USDC', 'POL', 'AVAX'])->get();

        $hub = array_filter([$usdt, $bdt]);
        $sort = 0;

        // Every crypto pairs with each hub asset (both directions).
        foreach ($cryptos as $crypto) {
            foreach ($hub as $h) {
                $this->pair($crypto, $h, $sort++);
                $this->pair($h, $crypto, $sort++);
            }
        }

        // USDT <-> BDT.
        if ($usdt && $bdt) {
            $this->pair($usdt, $bdt, $sort++);
            $this->pair($bdt, $usdt, $sort++);
        }

        // USDT <-> fiat (USD, EUR).
        foreach (array_filter([$usd, $eur]) as $fiat) {
            $this->pair($usdt, $fiat, $sort++);
            $this->pair($fiat, $usdt, $sort++);
        }
    }

    private function pair(?Asset $from, ?Asset $to, int $sort): void
    {
        if (! $from || ! $to || $from->id === $to->id) {
            return;
        }

        TradingPair::updateOrCreate(
            ['from_asset_id' => $from->id, 'to_asset_id' => $to->id],
            ['is_active' => true, 'sort' => $sort],
        );
    }
}
