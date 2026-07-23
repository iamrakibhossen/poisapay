<?php

declare(strict_types=1);

namespace App\Domain\Custody;

use App\Domain\Chain\Evm\Evm;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Bip32;
use App\Domain\Custody\Crypto\TronAddress;
use App\Enums\ChainType;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Testnet/demo {@see SignerKeyProvider}: the BIP32 master seed comes from config
 * (`poisapay.custody.seed`), ideally as a Laravel-encrypted string (encrypt at
 * rest), falling back to a hex seed for local dev. The seed is decoded in-process
 * only to derive keys and is never persisted. For production, bind a KMS/HSM
 * implementation of {@see SignerKeyProvider} instead — call sites don't change.
 *
 * Derivation layout per chain (coin = BIP44 coin type, 195 for TRON):
 *   account      m/44'/coin'/0'
 *   deposit i    m/44'/coin'/0'/0/{i}
 *   hot wallet   m/44'/coin'/0'/1/0
 */
class EnvSeedSignerKeyProvider implements SignerKeyProvider
{
    public function __construct(private readonly Bip32 $bip32) {}

    public function accountXpub(ChainType $chain): string
    {
        return $this->bip32->serialize($this->bip32->neuter($this->account($chain)), false);
    }

    public function derivePrivateKey(ChainType $chain, int $index): string
    {
        $node = $this->bip32->ckdPriv($this->bip32->ckdPriv($this->account($chain), 0), $index);

        return bin2hex($this->bip32->privateKey($node));
    }

    public function hotWalletPrivateKey(ChainType $chain): string
    {
        return bin2hex($this->bip32->privateKey($this->hotWallet($chain)));
    }

    public function hotWalletAddress(ChainType $chain): string
    {
        $pub = $this->bip32->compressedPublic($this->hotWallet($chain));

        return match (true) {
            $chain === ChainType::Tron => TronAddress::fromPublicKey($pub),
            $chain->isEvm() => Evm::toChecksumAddress(TronAddress::evmHex($pub)),
            default => throw new RuntimeException('Address encoding not implemented for '.$chain->value),
        };
    }

    /** @return array<string, mixed> */
    private function account(ChainType $chain): array
    {
        $master = $this->bip32->masterFromSeed($this->seed());

        return $this->bip32->derivePath($master, [
            44 + Bip32::HARDENED,
            $chain->coinType() + Bip32::HARDENED,
            0 + Bip32::HARDENED,
        ]);
    }

    /** @return array<string, mixed> */
    private function hotWallet(ChainType $chain): array
    {
        return $this->bip32->ckdPriv($this->bip32->ckdPriv($this->account($chain), 1), 0);
    }

    private function seed(): string
    {
        $raw = (string) config('poisapay.custody.seed');
        if ($raw === '') {
            throw new RuntimeException('Custody seed is not configured (poisapay.custody.seed).');
        }

        // Prefer a Laravel-encrypted value; fall back to a raw hex seed for dev.
        try {
            $raw = Crypt::decryptString($raw);
        } catch (DecryptException) {
            // not an encrypted payload — treat as-is
        }

        $seed = ctype_xdigit($raw) ? hex2bin($raw) : $raw;
        if ($seed === false || strlen($seed) < 16) {
            throw new RuntimeException('Custody seed is too short; provide >= 16 bytes of entropy.');
        }

        return $seed;
    }
}
