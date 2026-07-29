<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Custody\CustodyMode;
use App\Domain\Custody\CustodyReadiness;
use App\Enums\ChainType;
use App\Models\Asset;
use App\Models\Chain;
use Illuminate\Console\Command;
use Throwable;

/**
 * Custody readiness doctor: verifies the custody-mode flags agree and that every
 * chain with active token assets has a working signer, funded gas wallet, and
 * reachable RPC. Use before flipping to live custody. `--sync` writes the
 * settings flag to match the config source of truth.
 */
class CustodyDoctorCommand extends Command
{
    protected $signature = 'paishapay:custody-doctor {--sync : Align the custody_simulated setting with the config/env source of truth}';

    protected $description = 'Check custody-mode consistency + per-chain withdrawal readiness (signer, gas, RPC).';

    public function handle(CustodyReadiness $readiness): int
    {
        if ($this->option('sync')) {
            CustodyMode::syncSetting();
            $this->info('Synced the custody_simulated setting to '.(CustodyMode::isSimulated() ? 'SIMULATED' : 'LIVE').'.');
        }

        $this->line('Mode: <comment>'.(CustodyMode::isLive() ? 'LIVE' : 'SIMULATED').'</comment>');

        try {
            CustodyMode::assertConsistent();
            $this->info('✓ Custody-mode flags are consistent.');
        } catch (Throwable $e) {
            $this->error('✗ '.$e->getMessage());

            return self::FAILURE;
        }

        if (CustodyMode::isSimulated()) {
            $this->warn('Custody is SIMULATED — withdrawals do not broadcast on-chain. Set POISAPAY_CUSTODY_LIVE=true to go live.');

            return self::SUCCESS;
        }

        $ok = true;
        foreach ($this->chainsWithTokens() as $chain) {
            $problems = $readiness->check($chain);
            if ($problems === []) {
                $this->info("✓ {$chain->label()} ready.");
            } else {
                $ok = false;
                $this->error("✗ {$chain->label()} NOT ready:");
                foreach ($problems as $p) {
                    $this->line('    - '.$p);
                }
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<ChainType> */
    private function chainsWithTokens(): array
    {
        $keys = Asset::whereNotNull('contract_address')->where('is_active', true)
            ->join('chains', 'assets.chain_id', '=', 'chains.id')
            ->distinct()->pluck('chains.key')->all();

        $out = [];
        foreach ($keys as $key) {
            $type = $key instanceof ChainType ? $key : ChainType::tryFrom((string) $key);
            if ($type) {
                $out[] = $type;
            }
        }

        return $out ?: array_values(array_filter(
            Chain::where('is_active', true)->pluck('key')->all(),
            fn ($k) => (bool) ($k instanceof ChainType ? $k : ChainType::tryFrom((string) $k)),
        ));
    }
}
