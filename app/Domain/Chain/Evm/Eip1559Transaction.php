<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

/**
 * EIP-1559 (type 0x02) transaction builder (Wave 2). Produces the keccak signing
 * hash and, given an secp256k1 signature, the raw signed transaction for
 * eth_sendRawTransaction. All numeric fields are decimal (wei / base-unit) strings.
 * Access list is always empty (simple value/ERC-20 transfers).
 */
final class Eip1559Transaction
{
    public function __construct(
        public readonly int $chainId,
        public readonly string $nonce,
        public readonly string $maxPriorityFeePerGas,
        public readonly string $maxFeePerGas,
        public readonly string $gasLimit,
        public readonly string $to,
        public readonly string $value,
        public readonly string $data = '0x',
    ) {}

    /**
     * The 9 unsigned RLP fields (chainId … accessList).
     *
     * @return array<int, string|array<mixed>>
     */
    private function unsignedFields(): array
    {
        return [
            Evm::intToBytes((string) $this->chainId),
            Evm::intToBytes($this->nonce),
            Evm::intToBytes($this->maxPriorityFeePerGas),
            Evm::intToBytes($this->maxFeePerGas),
            Evm::intToBytes($this->gasLimit),
            (string) hex2bin(Evm::strip0x($this->to)),
            Evm::intToBytes($this->value),
            (string) hex2bin(Evm::strip0x($this->data)),
            [], // accessList
        ];
    }

    /** keccak(0x02 || rlp(unsignedFields)) as hex — the digest the signer signs. */
    public function signingHash(): string
    {
        $payload = "\x02".Rlp::encode($this->unsignedFields());

        return bin2hex(Evm::keccak($payload));
    }

    /**
     * Serialize the signed transaction to a 0x raw-tx string. $r/$s are 32-byte hex
     * values from the signature; $yParity is the recovery bit (0 or 1) — for typed
     * transactions v IS the parity (no +27).
     */
    public function serialize(string $r, string $s, int $yParity): string
    {
        $fields = $this->unsignedFields();
        $fields[] = Evm::intToBytes((string) $yParity);
        $fields[] = Evm::intToBytes(Evm::hexToInt($r));
        $fields[] = Evm::intToBytes(Evm::hexToInt($s));

        return '0x02'.bin2hex(Rlp::encode($fields));
    }
}
