<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\Abi;
use App\Domain\Chain\Evm\Eip1559Transaction;
use App\Domain\Chain\Evm\Evm;
use App\Domain\Chain\Evm\Rlp;
use App\Domain\Custody\Crypto\Secp256k1Signer;

// keccak-256 canonical empty-string vector.
it('computes keccak-256 correctly', function () {
    expect(bin2hex(Evm::keccak('')))->toBe('c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470');
});

it('derives ABI selectors and event topics', function () {
    expect(Evm::selector('transfer(address,uint256)'))->toBe('a9059cbb')
        ->and(Abi::transferEventTopic())->toBe('0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef');
});

// EIP-55 checksum reference addresses.
it('checksums addresses per EIP-55', function () {
    foreach ([
        '0x5aaeb6053f3e94c9b9a09f33669435e7ef1beaed' => '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed',
        '0xfb6916095ca1df60bb79ce92ce3ea74c37c5d359' => '0xfB6916095ca1df60bB79Ce92cE3Ea74c37c5d359',
        '0xdbf03b407c01e7cd3cbea99509d93f8dddc8c6fb' => '0xdbF03B407c01E7cD3CBea99509d93f8DDDC8C6FB',
    ] as $lower => $checksummed) {
        expect(Evm::toChecksumAddress($lower))->toBe($checksummed);
    }
});

// RLP spec vectors.
it('encodes RLP per spec', function () {
    expect(bin2hex(Rlp::encode('dog')))->toBe('83646f67')
        ->and(bin2hex(Rlp::encode(['cat', 'dog'])))->toBe('c88363617483646f67')
        ->and(bin2hex(Rlp::encode('')))->toBe('80')
        ->and(bin2hex(Rlp::encode("\x00")))->toBe('00')
        ->and(bin2hex(Rlp::encode("\x0f")))->toBe('0f')
        ->and(bin2hex(Rlp::encode("\x04\x00")))->toBe('820400')
        ->and(bin2hex(Rlp::encode([])))->toBe('c0');
});

it('converts integers to minimal big-endian bytes', function () {
    expect(bin2hex(Evm::intToBytes('0')))->toBe('')
        ->and(bin2hex(Evm::intToBytes('1024')))->toBe('0400')
        ->and(Evm::hexToInt('0x0400'))->toBe('1024')
        ->and(Evm::intToHex('255'))->toBe('0xff');
});

it('encodes an ERC-20 transfer call', function () {
    $data = Abi::erc20Transfer('0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed', '1000000');
    expect($data)->toStartWith('0xa9059cbb')
        ->and(strlen(Evm::strip0x($data)))->toBe(8 + 64 + 64) // selector + 2 words
        ->and(substr($data, -64))->toBe(Evm::pad32('f4240')); // 1000000 = 0xf4240
});

it('decodes an ERC-20 Transfer log', function () {
    $decoded = Abi::decodeTransferLog([
        'topics' => [
            Abi::transferEventTopic(),
            '0x000000000000000000000000fb6916095ca1df60bb79ce92ce3ea74c37c5d359',
            '0x0000000000000000000000005aaeb6053f3e94c9b9a09f33669435e7ef1beaed',
        ],
        'data' => '0x00000000000000000000000000000000000000000000000000000000000f4240',
    ]);
    expect($decoded['to'])->toBe('0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed')
        ->and($decoded['amount'])->toBe('1000000');
});

it('signs and serializes an EIP-1559 transaction', function () {
    $tx = new Eip1559Transaction(
        chainId: 1, nonce: '0', maxPriorityFeePerGas: '1000000000', maxFeePerGas: '30000000000',
        gasLimit: '21000', to: '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed', value: '1000000000000000000',
    );
    $hash = $tx->signingHash();
    expect(strlen($hash))->toBe(64);

    // Deterministic test key (never a real key).
    $priv = str_repeat('11', 32);
    $sig = app(Secp256k1Signer::class)->sign($hash, $priv);
    $raw = $tx->serialize(substr($sig, 0, 64), substr($sig, 64, 64), hexdec(substr($sig, 128, 2)));

    expect($sig)->toHaveLength(130)
        ->and($raw)->toStartWith('0x02')
        ->and(ctype_xdigit(Evm::strip0x($raw)))->toBeTrue();
});
