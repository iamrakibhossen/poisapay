<?php

declare(strict_types=1);

namespace App\Domain\Custody\Contracts;

use App\Enums\ChainType;

/**
 * Supplies signing key material for the isolated custody zone. The rest of the
 * platform NEVER sees a private key directly — it asks this provider to derive
 * one for a specific purpose. The env-encrypted-seed implementation is for
 * testnet/demo; a KMS/HSM implementation swaps in for production without
 * touching call sites (only this binding changes).
 *
 * Invariants: extended PRIVATE keys and raw seeds never leave this boundary and
 * are never persisted; only the account xpub (public) is published for the
 * online zone to derive deposit addresses.
 */
interface SignerKeyProvider
{
    /**
     * The account-level extended PUBLIC key (xpub at m/44'/coin'/0') for a chain.
     * Registered as a CustodyXpub so the online zone can derive deposit addresses.
     */
    public function accountXpub(ChainType $chain): string;

    /**
     * Raw 32-byte private key (hex) for the deposit address at receive index
     * m/44'/coin'/0'/0/{index}. Used to sweep collected deposits.
     */
    public function derivePrivateKey(ChainType $chain, int $index): string;

    /** Raw 32-byte private key (hex) of the hot wallet (m/44'/coin'/0'/1/0) that funds withdrawals. */
    public function hotWalletPrivateKey(ChainType $chain): string;

    /** The hot wallet's on-chain address for a chain (public — safe to expose). */
    public function hotWalletAddress(ChainType $chain): string;
}
