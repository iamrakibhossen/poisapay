<?php

declare(strict_types=1);

namespace App\Domain\Custody\Crypto;

use Elliptic\EC;

/**
 * secp256k1 ECDSA signing for TRON transactions. TRON signs the transaction's
 * txID (sha256 of the protobuf raw_data) and expects a 65-byte [r || s || v]
 * recoverable signature. Canonical (low-S) signatures are enforced.
 */
final class Secp256k1Signer
{
    private EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    /**
     * Sign a 32-byte hash (hex) with a private key (hex); returns 65-byte hex [r||s||v].
     */
    public function sign(string $hashHex, string $privateKeyHex): string
    {
        $signature = $this->ec->keyFromPrivate($privateKeyHex)->sign($hashHex, ['canonical' => true]);

        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = str_pad(dechex($signature->recoveryParam), 2, '0', STR_PAD_LEFT);

        return $r.$s.$v;
    }

    /** Verify a 65-byte hex signature against a public key (hex) for a hash (hex). */
    public function verify(string $hashHex, string $signatureHex, string $publicKeyHex): bool
    {
        if (strlen($signatureHex) !== 130) {
            return false;
        }

        return $this->ec->keyFromPublic($publicKeyHex, 'hex')->verify($hashHex, [
            'r' => substr($signatureHex, 0, 64),
            's' => substr($signatureHex, 64, 64),
        ]);
    }
}
