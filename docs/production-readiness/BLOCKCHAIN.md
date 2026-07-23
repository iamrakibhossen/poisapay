# PoisaPay — EVM Blockchain Infrastructure (Wave 2)

Real Ethereum/BSC custody built against a vendor-neutral JSON-RPC provider, mirroring
the existing live TRON implementation and reusing the shared ledger actions unchanged.
No vendor credentials required to build or test — the vendor is chosen purely by the RPC
URL configured in `rpc_endpoints` (Infura / Alchemy / QuickNode / self-hosted / Anvil).

## The provider seam
`App\Domain\Chain\Evm\Contracts\BlockchainProvider` — a thin surface over standard
JSON-RPC (`eth_blockNumber`, `eth_getLogs`, `eth_getTransactionReceipt`, `eth_call`,
`eth_getBalance`, `eth_getTransactionCount`, `eth_maxPriorityFeePerGas`,
`eth_estimateGas`, `eth_sendRawTransaction`).

| Driver | Class | Use |
|---|---|---|
| `http` (default) | `HttpBlockchainProvider` | Production — per-request failover across the chain's `rpc_endpoints` (priority order) + retry |
| `fake` | `FakeBlockchainProvider` | Deterministic tests + local dev — in-memory chain state |

Bound via `config/providers.php` → `blockchain`; swap vendors by editing `rpc_endpoints`,
never code.

## EVM primitives (unit-tested against canonical vectors)
`App\Domain\Chain\Evm\*`: `Evm` (keccak-256, EIP-55 checksums, ABI selectors, int↔bytes),
`Rlp` (RLP encoder), `Abi` (ERC-20 `transfer` encode + `Transfer` log decode),
`Eip1559Transaction` (type-0x02 build → keccak signing hash → signed raw tx). Signing uses
the existing `Secp256k1Signer`; addresses reuse `TronAddress::evmHex` + EIP-55.

## Pipelines (mirror TRON; reuse shared ledger actions)
- **Address derivation** — `EvmAddressDeriver` (BIP32 CKDpub → keccak → EIP-55), routed by
  `ChainRoutingAddressDeriver` when custody is live; hot-wallet address added to
  `EnvSeedSignerKeyProvider`.
- **Deposits** — `ScanEvmDepositsAction` (ERC-20 `Transfer` logs to watched addresses) →
  `AdvanceEvmDepositsAction` (receipt-based confirmations, reorg/revert → orphan) →
  shared `CreditDepositAction`.
- **Withdrawals** — `NonceManager` (reconciled reserved nonces) + `GasEstimationService`
  (EIP-1559 tip + base-fee headroom) + `EvmWithdrawalSigner` (build/sign/broadcast) →
  `AdvanceEvmWithdrawalsAction` (confirm → shared `SettleWithdrawalAction`).
- **Hot wallet + reconciliation** — `HotWalletManager` syncs the native gas balance
  (alerts when low) and compares on-chain hot ERC-20 balance vs ledger treasury.
- **Orchestration** — `EvmCustodyTickJob` (queued, `tries=3` + backoff) runs the full cycle
  per active EVM chain and updates RPC-endpoint health; scheduled every minute; **no-op while
  `custody_simulated`**, so it's safe to schedule now.

## Config
`config/poisapay.php` → `custody.ethereum` / `custody.bsc` (rpc, chain_id, usdt_contract,
confirmations), `custody.evm_scan_range`, `custody.evm_transfer_gas`. Point `*_RPC` at
`http://127.0.0.1:8545` to test against Anvil.

## Testing
- `tests/Unit/EvmPrimitivesTest.php` — keccak / EIP-55 / RLP / ABI / EIP-1559 vs known vectors.
- `tests/Feature/EvmCustodyTest.php` — real derivation, deposit detect→credit, reorg-orphan,
  withdrawal sign→broadcast→settle, nonce increment, gas-low alert (via the fake provider).
- `tests/Feature/AnvilIntegrationTest.php` — real JSON-RPC against a local node, **skipped
  unless reachable**. Run it:
  ```bash
  anvil &                       # Foundry local node on :8545
  php artisan test tests/Feature/AnvilIntegrationTest.php
  ```

## Going live (config-only)
1. Add production RPC URLs to `rpc_endpoints` (Infura/Alchemy/QuickNode).
2. Seed the Ethereum/BSC USDT `Asset` rows + `CustodyXpub` + a funded hot wallet + gas wallet.
3. Set `POISAPAY_CUSTODY_LIVE=true` and a KMS-backed `SignerKeyProvider` (Wave 1).
4. The scheduled `EvmCustodyTickJob` begins detecting deposits and settling withdrawals.

## Not included (documented follow-ups)
- **On-chain deposit sweep for EVM** (signing an ERC-20 transfer from each deposit address to
  the hot wallet, after gas-funding it) — the primitives exist (`derivePrivateKey` +
  `Eip1559Transaction`); it's a distinct custody step. The ledger sweep leg is already handled
  by the chain-agnostic `SweepDepositAction`.
- KMS/HSM `SignerKeyProvider` (Wave 1) — the env-seed signer is testnet-only.
