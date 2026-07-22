<?php

declare(strict_types=1);

namespace App\Domain\Custody\Crypto;

use BN\BN;
use Elliptic\EC;
use InvalidArgumentException;
use RuntimeException;

/**
 * BIP32 hierarchical-deterministic key derivation over secp256k1 (mainnet
 * xprv/xpub version bytes). Supports:
 *  - master key from seed
 *  - private child derivation (CKDpriv), hardened + non-hardened
 *  - public child derivation (CKDpub) — the online zone derives deposit
 *    addresses from an account xpub WITHOUT ever touching a private key
 *  - neuter (private node → public node) and Base58Check serialize/parse
 *
 * A node is an array: ['private'=>bool, 'key'=>bin(32 priv | 33 comp-pub),
 * 'chain'=>bin32, 'depth'=>int, 'index'=>int, 'parentFp'=>bin4].
 */
final class Bip32
{
    public const HARDENED = 0x80000000;

    private const VERSION_XPRV = "\x04\x88\xAD\xE4";

    private const VERSION_XPUB = "\x04\x88\xB2\x1E";

    private EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    /** @return array<string, mixed> master node from a binary seed */
    public function masterFromSeed(string $seed): array
    {
        $I = hash_hmac('sha512', $seed, 'Bitcoin seed', true);

        return [
            'private' => true,
            'key' => substr($I, 0, 32),
            'chain' => substr($I, 32, 32),
            'depth' => 0,
            'index' => 0,
            'parentFp' => "\x00\x00\x00\x00",
        ];
    }

    /**
     * Derive a child private node (CKDpriv). Hardened when $index >= HARDENED.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function ckdPriv(array $node, int $index): array
    {
        if (! $node['private']) {
            throw new InvalidArgumentException('CKDpriv requires a private node.');
        }

        $hardened = $index >= self::HARDENED;
        $data = $hardened
            ? "\x00".$node['key'].pack('N', $index)
            : $this->compressedPublic($node).pack('N', $index);

        $I = hash_hmac('sha512', $data, $node['chain'], true);
        $IL = substr($I, 0, 32);
        $IR = substr($I, 32, 32);

        $ki = (new BN(bin2hex($IL), 16))->add(new BN(bin2hex($node['key']), 16))->umod($this->ec->n);
        if ($ki->isZero()) {
            throw new RuntimeException('Derived an invalid (zero) child key; advance the index.');
        }

        return [
            'private' => true,
            'key' => $this->pad32($ki),
            'chain' => $IR,
            'depth' => $node['depth'] + 1,
            'index' => $index,
            'parentFp' => $this->fingerprint($node),
        ];
    }

    /**
     * Derive a child public node (CKDpub). Non-hardened only — this is how the
     * online zone turns an account xpub into deposit addresses without a seed.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function ckdPub(array $node, int $index): array
    {
        if ($index >= self::HARDENED) {
            throw new InvalidArgumentException('CKDpub cannot derive a hardened child.');
        }

        $parentPubHex = bin2hex($this->compressedPublic($node));
        $data = hex2bin($parentPubHex).pack('N', $index);

        $I = hash_hmac('sha512', $data, $node['chain'], true);
        $IL = substr($I, 0, 32);
        $IR = substr($I, 32, 32);

        $point = $this->ec->g->mul(new BN(bin2hex($IL), 16))
            ->add($this->ec->keyFromPublic($parentPubHex, 'hex')->getPublic());

        if ($point->isInfinity()) {
            throw new RuntimeException('Derived point at infinity; advance the index.');
        }

        return [
            'private' => false,
            'key' => hex2bin($point->encode('hex', true)),
            'chain' => $IR,
            'depth' => $node['depth'] + 1,
            'index' => $index,
            'parentFp' => $this->fingerprint($node),
        ];
    }

    /**
     * Follow a BIP32 path of child indexes from a node.
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, int>  $path  each entry an index (add HARDENED for hardened)
     * @return array<string, mixed>
     */
    public function derivePath(array $node, array $path): array
    {
        foreach ($path as $index) {
            $node = $this->ckdPriv($node, $index);
        }

        return $node;
    }

    /**
     * Neuter a node (strip the private key, keep public derivation ability).
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function neuter(array $node): array
    {
        if (! $node['private']) {
            return $node;
        }

        return [
            'private' => false,
            'key' => $this->compressedPublic($node),
            'chain' => $node['chain'],
            'depth' => $node['depth'],
            'index' => $node['index'],
            'parentFp' => $node['parentFp'],
        ];
    }

    /**
     * Serialize a node to Base58Check (xprv when private + $asPrivate, else xpub).
     *
     * @param  array<string, mixed>  $node
     */
    public function serialize(array $node, bool $asPrivate = false): string
    {
        if ($asPrivate && ! $node['private']) {
            throw new InvalidArgumentException('Cannot serialize a public node as xprv.');
        }

        $version = $asPrivate ? self::VERSION_XPRV : self::VERSION_XPUB;
        $keyData = $asPrivate ? "\x00".$node['key'] : $this->compressedPublic($node);

        $payload = $version
            .chr($node['depth'] & 0xFF)
            .$node['parentFp']
            .pack('N', $node['index'])
            .$node['chain']
            .$keyData;

        return Base58::encodeCheck($payload);
    }

    /**
     * Parse a Base58Check xprv/xpub into a node.
     *
     * @return array<string, mixed>
     */
    public function parse(string $extended): array
    {
        $raw = Base58::decodeCheck($extended);
        if (strlen($raw) !== 78) {
            throw new InvalidArgumentException('Invalid extended key length.');
        }

        $version = substr($raw, 0, 4);
        $private = $version === self::VERSION_XPRV;
        if (! $private && $version !== self::VERSION_XPUB) {
            throw new InvalidArgumentException('Unrecognised extended key version.');
        }

        $keyData = substr($raw, 45, 33);

        return [
            'private' => $private,
            'key' => $private ? substr($keyData, 1) : $keyData,
            'chain' => substr($raw, 13, 32),
            'depth' => ord($raw[4]),
            'index' => unpack('N', substr($raw, 9, 4))[1],
            'parentFp' => substr($raw, 5, 4),
        ];
    }

    /**
     * Compressed public key (33 bytes) for a node.
     *
     * @param  array<string, mixed>  $node
     */
    public function compressedPublic(array $node): string
    {
        if (! $node['private']) {
            return $node['key'];
        }

        return hex2bin($this->ec->keyFromPrivate(bin2hex($node['key']))->getPublic(true, 'hex'));
    }

    /**
     * Raw 32-byte private key of a node.
     *
     * @param  array<string, mixed>  $node
     */
    public function privateKey(array $node): string
    {
        if (! $node['private']) {
            throw new InvalidArgumentException('Node has no private key.');
        }

        return $node['key'];
    }

    /** @param array<string, mixed> $node */
    private function fingerprint(array $node): string
    {
        $pub = $this->compressedPublic($node);
        $hash160 = hash('ripemd160', hash('sha256', $pub, true), true);

        return substr($hash160, 0, 4);
    }

    private function pad32(BN $bn): string
    {
        return hex2bin(str_pad($bn->toString(16), 64, '0', STR_PAD_LEFT));
    }
}
