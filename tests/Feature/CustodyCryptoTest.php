<?php

declare(strict_types=1);

use App\Domain\Custody\ChainRoutingAddressDeriver;
use App\Domain\Custody\Contracts\AddressDeriver;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Base58;
use App\Domain\Custody\Crypto\Bip32;
use App\Domain\Custody\Crypto\TronAddress;
use App\Domain\Custody\DeterministicAddressDeriver;
use App\Domain\Custody\EnvSeedSignerKeyProvider;
use App\Domain\Custody\TronAddressDeriver;
use App\Enums\ChainType;
use Elliptic\EC;

// A fixed testnet seed (never a real one).
const TEST_SEED_HEX = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

function compressedPubHex(string $privHex): string
{
    return (new EC('secp256k1'))->keyFromPrivate($privHex)->getPublic(true, 'hex');
}

it('round-trips Base58Check', function () {
    $payload = random_bytes(21);
    expect(Base58::decodeCheck(Base58::encodeCheck($payload)))->toBe($payload);
});

it('rejects a corrupted Base58Check checksum', function () {
    $good = Base58::encodeCheck(random_bytes(21));
    Base58::decodeCheck(substr($good, 0, -1).($good[-1] === 'z' ? 'x' : 'z'));
})->throws(RuntimeException::class);

it('matches BIP32 official test vector 1 (master + hardened + public CKD)', function () {
    $b = new Bip32;
    $m = $b->masterFromSeed(hex2bin('000102030405060708090a0b0c0d0e0f'));

    // Master public key + chain code are the canonical vector-1 values.
    expect(bin2hex($b->compressedPublic($m)))->toBe('0339a36013301597daef41fbe593a02cc513d0b55527ec2df1050e2e8ff49c85c2')
        ->and(bin2hex($m['chain']))->toBe('873dff81c02f525623fd1fe5167eac3a55a049de3d314bb42ee227ffed37d508');

    // m/0'  (hardened) private + public serialization.
    $acct = $b->ckdPriv($m, 0 + Bip32::HARDENED);
    expect($b->serialize($acct, true))->toBe('xprv9uHRZZhk6KAJC1avXpDAp4MDc3sQKNxDiPvvkX8Br5ngLNv1TxvUxt4cV1rGL5hj6KCesnDYUhd7oWgT11eZG7XnxHrnYeSvkzY7d2bhkJ7')
        ->and($b->serialize($b->neuter($acct), false))->toBe('xpub68Gmy5EdvgibQVfPdqkBBCHxA5htiqg55crXYuXoQRKfDBFA1WEjWgP6LHhwBZeNK1VTsfTFUHCdrfp1bgwQ9xv5ski8PX9rL2dZXvgGDnw');

    // m/0'/1 derived by PUBLIC-ONLY CKD from the account xpub == the vector xpub.
    $child = $b->ckdPub($b->parse('xpub68Gmy5EdvgibQVfPdqkBBCHxA5htiqg55crXYuXoQRKfDBFA1WEjWgP6LHhwBZeNK1VTsfTFUHCdrfp1bgwQ9xv5ski8PX9rL2dZXvgGDnw'), 1);
    expect($b->serialize($child, false))->toBe('xpub6ASuArnXKPbfEwhqN6e3mwBcDTgzisQN1wXN9BJcM47sSikHjJf3UFHKkNAWbWMiGj7Wf5uMash7SyYq527Hqck2AxYysAA7xmALppuCkwQ');
});

it('derives the correct 20-byte hash (EVM/TRON) for a known key', function () {
    // Hardhat account #0 → 0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266.
    $pub = hex2bin(compressedPubHex('ac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80'));
    expect(TronAddress::evmHex($pub))->toBe('f39fd6e51aad88f6f4ce6ab8827279cfffb92266');
});

it('produces a structurally valid TRON address', function () {
    $provider = new EnvSeedSignerKeyProvider(new Bip32);
    config(['poisapay.custody.seed' => TEST_SEED_HEX]);
    $addr = (new TronAddressDeriver(new Bip32))->derive(ChainType::Tron, $provider->accountXpub(ChainType::Tron), 0);

    expect($addr[0])->toBe('T')->and(strlen($addr))->toBe(34);
    $payload = TronAddress::decode($addr);          // throws unless 0x41 + 20 bytes, valid checksum
    expect(strlen($payload))->toBe(21)->and($payload[0])->toBe("\x41");
});

it('CUSTODY INVARIANT: the online xpub-derived address equals the offline signing key address', function () {
    config(['poisapay.custody.seed' => TEST_SEED_HEX]);
    $provider = new EnvSeedSignerKeyProvider(new Bip32);
    $deriver = new TronAddressDeriver(new Bip32);

    $xpub = $provider->accountXpub(ChainType::Tron);

    foreach ([0, 1, 5, 42] as $index) {
        $onlineAddress = $deriver->derive(ChainType::Tron, $xpub, $index);
        $offlineAddress = TronAddress::fromPublicKey(
            hex2bin(compressedPubHex($provider->derivePrivateKey(ChainType::Tron, $index)))
        );

        expect($onlineAddress)->toBe($offlineAddress);
    }
});

it('routes TRON to real derivation only when custody is live', function () {
    config(['poisapay.custody.seed' => TEST_SEED_HEX]);
    $xpub = (new EnvSeedSignerKeyProvider(new Bip32))->accountXpub(ChainType::Tron);

    $router = new ChainRoutingAddressDeriver(new TronAddressDeriver(new Bip32), new DeterministicAddressDeriver);

    config(['poisapay.custody_simulated' => true]);
    $simulated = $router->derive(ChainType::Tron, $xpub, 0);
    expect($simulated)->toBe((new DeterministicAddressDeriver)->derive(ChainType::Tron, $xpub, 0));

    config(['poisapay.custody_simulated' => false]);
    $live = $router->derive(ChainType::Tron, $xpub, 0);
    expect($live)->toBe((new TronAddressDeriver(new Bip32))->derive(ChainType::Tron, $xpub, 0))
        ->and($live)->not->toBe($simulated);
});

it('binds the routing deriver + env signer by default', function () {
    expect(app(AddressDeriver::class))->toBeInstanceOf(ChainRoutingAddressDeriver::class)
        ->and(app(SignerKeyProvider::class))->toBeInstanceOf(EnvSeedSignerKeyProvider::class);
});
