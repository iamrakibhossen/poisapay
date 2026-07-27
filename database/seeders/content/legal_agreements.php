<?php

declare(strict_types=1);

return [
    [
        'slug' => 'terms',
        'title' => 'Terms of Service',
        'meta_description' => 'PaishaPay Terms of Service — the master agreement covering your custodial wallet, cards, exchange, fees, KYC, and account rules.',
        'content' => <<<'HTML'
<p>These Terms of Service (the "Terms") form the master agreement between you and PaishaPay, a custodial digital-asset wallet and payments platform offering a USD and crypto wallet, an internal exchange, virtual cards, merchant payments, and a commerce module. By creating an account or using any part of PaishaPay, you agree to these Terms and to the related policies cross-linked throughout this document. Please read them carefully; they explain your rights, your responsibilities, and the limits of what PaishaPay does.</p>

<h2>1. Eligibility</h2>
<p>You must be at least <strong>18 years old</strong> and have the legal capacity to enter into a binding agreement. You may not use PaishaPay if you are a sanctioned person or located in a prohibited or high-risk jurisdiction, as described in our <a href="/pages/aml-policy">AML Policy</a>. We may refuse, suspend, or close access at any time where eligibility cannot be established.</p>

<h2>2. Your Account and Credentials</h2>
<p>You are responsible for keeping your login credentials, two-factor authentication (TOTP), recovery codes, and device access secure. Signup requires <strong>email verification</strong>, and sensitive actions may require a 6-digit one-time passcode. You must promptly report any unauthorized access. Activity carried out through your authenticated session is treated as your own. See our <a href="/pages/security">Security</a> notice for details.</p>

<h2>3. Custodial Wallet</h2>
<p>PaishaPay holds your balances <strong>custodially</strong>. Balances are derived from an immutable double-entry ledger and are shown as <strong>available</strong> and <strong>locked</strong> amounts. The full terms of custody, supported assets, and deposits are set out in the <a href="/pages/wallet-agreement">Wallet Agreement</a>. PaishaPay is <strong>not a bank</strong>, does not pay interest, and balances are <strong>not insured</strong> by any deposit-guarantee scheme.</p>

<h2>4. Accurate Identity and KYC</h2>
<p>You must provide accurate, current, and complete identity information. Access to features scales with your verification tier, and withdrawals or card issuance require verification as described in our <a href="/pages/kyc-policy">KYC Policy</a>. Providing false information, or refusing lawful re-verification or source-of-funds requests, may result in restriction or closure of your account.</p>

<h2>5. Acceptable Use</h2>
<p>You agree to use PaishaPay only for lawful purposes. Prohibited activity includes fraud, money laundering, sanctions evasion, and any use that violates applicable law. A summary of prohibited conduct is available in our <a href="/pages/acceptable-use">Acceptable Use Policy</a>. We monitor transactions on a risk basis and may freeze value movement where required by our <a href="/pages/aml-policy">AML Policy</a>.</p>

<h2>6. Fees and Limits</h2>
<p>PaishaPay charges configurable fees for certain services — including deposits, withdrawals, exchange spreads, card transactions, and merchant processing. All applicable <strong>fees and limits are shown before you confirm</strong> a transaction and may be updated from time to time. Illustrative defaults appear in the relevant policy pages, but the figure displayed at the moment of confirmation is authoritative.</p>

<h2>7. Digital-Asset Volatility</h2>
<p>Digital assets are volatile and their value can fall sharply and without notice. You use PaishaPay's wallet and exchange at your own risk. Read our <a href="/pages/risk-disclosure">Risk Disclosure</a> and <a href="/pages/crypto-risk">Crypto Risk</a> statements before transacting.</p>

<h2>8. Cards and Exchange</h2>
<p>Virtual card use is governed by the <a href="/pages/cardholder-agreement">Cardholder Agreement</a>. Internal asset swaps are governed by the <a href="/pages/exchange-terms">Exchange Terms</a>. Note that the Exchange supports <strong>cryptocurrency-to-cryptocurrency conversions only</strong>.</p>

<h2>9. Suspension and Termination</h2>
<p>We may suspend or terminate access where required by law, by risk, or by breach of these Terms, as described in our <a href="/pages/termination-policy">Termination Policy</a>. On closure, we will make reasonable efforts to allow you to withdraw eligible balances, subject to compliance holds.</p>

<h2>10. Changes to These Terms</h2>
<p>We may update these Terms from time to time. Material changes will be notified through the platform or by email. Continued use after an update means you accept the revised Terms.</p>

<h2>11. Liability, Indemnity, and Governing Law</h2>
<p>Our liability to you is limited as set out in our <a href="/pages/limitation-of-liability">Limitation of Liability</a> statement, and you agree to the terms of our <a href="/pages/indemnification">Indemnification</a> policy. These Terms are governed by the laws of the jurisdiction in which PaishaPay is established and operates — with Bangladesh as our primary operating market — and the operator sets the specific governing jurisdiction and venue, as described in our <a href="/pages/governing-law">Governing Law</a> statement.</p>

<h2>12. Contact</h2>
<p>Questions about these Terms can be directed to <strong>support@poisapay.com</strong>, or for compliance matters, <strong>compliance@poisapay.com</strong>. Please also review our <a href="/pages/privacy">Privacy Policy</a> to understand how we handle your data.</p>
HTML,
    ],
    [
        'slug' => 'wallet-agreement',
        'title' => 'Wallet Agreement',
        'meta_description' => 'PaishaPay Wallet Agreement — how your custodial multi-asset wallet works: supported assets, available vs locked balances, deposits, and safeguarding.',
        'content' => <<<'HTML'
<p>This Wallet Agreement explains how your PaishaPay wallet works — the assets it supports, how your balances are calculated, how deposits are credited, and how your funds are safeguarded. It supplements the <a href="/pages/terms">Terms of Service</a>. By holding a balance with PaishaPay you accept the terms below.</p>

<h2>1. A Custodial Multi-Asset Wallet</h2>
<p>PaishaPay operates a <strong>custodial</strong> wallet: we hold and safeguard your assets on your behalf. Your wallet holds multiple assets at once, each with its own balance. PaishaPay is <strong>not a bank</strong>, does not pay <strong>interest</strong> on balances, and balances are <strong>not covered by any deposit-insurance scheme</strong>.</p>

<h2>2. Supported Assets</h2>
<p>Your wallet supports the following digital assets: <strong>USDT, USDC, TRX, ETH, BNB, POL, and AVAX</strong>. It also holds fiat-denominated balances in <strong>USD, BDT (Bangladeshi Taka), and EUR</strong>. Only these assets are supported; sending any other asset to PaishaPay may result in permanent loss.</p>

<h2>3. Available and Locked Balances</h2>
<p>Each asset shows two balances. Your <strong>available</strong> balance is spendable. Your <strong>locked</strong> balance is temporarily reserved for a pending operation — for example a card authorization hold or a withdrawal in progress. Your total balance for an asset is available plus locked. When a pending operation completes or is cancelled, the locked amount is released or finalised accordingly.</p>

<h2>4. Coin Pooling</h2>
<p>PaishaPay uses <strong>coin pooling</strong>: you hold <strong>one pooled balance per coin</strong>, regardless of how many networks that coin can travel on. The network only matters at the moment of <strong>deposit or withdrawal</strong>. For example, your USDT balance is a single figure even though USDT can be deposited or withdrawn across several supported networks.</p>

<h2>5. Balances Are Derived From the Ledger</h2>
<p>Your balances are <strong>derived from an immutable double-entry ledger</strong>, never stored as a mutable number that could be edited directly. Every movement of value writes matching ledger entries; money paths are idempotent and audited; and refunds or reversals are posted as <strong>reversing entries</strong> — ledger records are never deleted. This gives you an accurate, verifiable balance at all times.</p>

<h2>6. Deposits</h2>
<p>Deposits are made on-chain to a <strong>deposit address issued per network</strong>. Funds are credited to your available balance after the required number of network confirmations, and net of any configurable platform fee shown to you. The full rules — including confirmation counts and correct-network warnings — are set out in our <a href="/pages/deposit-policy">Deposit Policy</a>.</p>

<h2>7. Sending the Correct Asset and Network</h2>
<p><strong>You are responsible for sending the correct asset on the correct network.</strong> Sending an unsupported asset, or using an incompatible network for an address, can cause <strong>permanent and irrecoverable loss</strong>. PaishaPay cannot restore funds sent in error to the wrong network or an unsupported asset.</p>

<h2>8. Internal Transfers and Spending</h2>
<p>You can move value within PaishaPay — for example paying a merchant invoice or funding a card — directly from your wallet balance. Where a payment must settle in a different asset, we draw from your balances in your chosen priority order, converting when needed at the exchange effective rate. Internal transfers settle instantly on the ledger.</p>

<h2>9. Safeguarding and Cold Storage</h2>
<p>The majority of crypto assets are held in <strong>offline cold storage</strong>, with continuous reconciliation between ledger balances and custodial holdings. We apply operational and security controls to protect custodied funds, but no system is risk-free; please review our <a href="/pages/risk-disclosure">Risk Disclosure</a> and <a href="/pages/security">Security</a> notice.</p>

<h2>10. Related Policies</h2>
<p>This Agreement should be read together with the <a href="/pages/terms">Terms of Service</a>, <a href="/pages/deposit-policy">Deposit Policy</a>, <a href="/pages/withdrawal-policy">Withdrawal Policy</a>, and <a href="/pages/privacy">Privacy Policy</a>. For questions about your wallet, contact <strong>support@poisapay.com</strong>.</p>
HTML,
    ],
    [
        'slug' => 'cardholder-agreement',
        'title' => 'Cardholder Agreement',
        'meta_description' => 'PaishaPay Cardholder Agreement — how virtual cards work: funding, fees, controls, refunds, and why there are no chargebacks.',
        'content' => <<<'HTML'
<p>This Cardholder Agreement governs your use of a PaishaPay virtual card. It explains how the card is funded, the fees that apply, the controls available to you, and how refunds work. It supplements the <a href="/pages/terms">Terms of Service</a> and the <a href="/pages/wallet-agreement">Wallet Agreement</a>. By creating or using a card you accept these terms.</p>

<h2>1. Eligibility and Issuance</h2>
<p>Issuing a card requires <strong>Full KYC</strong> verification, as described in our <a href="/pages/kyc-policy">KYC Policy</a>. A card issuance fee may apply; it is <strong>operator-configurable</strong> and shown to you before you create the card (an operator may set it to free, or apply a fee for virtual or physical cards). Where applicable, cards are issued through licensed issuing partners.</p>

<h2>2. How the Card Is Funded</h2>
<p>Your card spends directly from your PaishaPay <strong>balance</strong>. At authorization, we use your matching <strong>fiat currency first (1:1, with no conversion)</strong>. If that is insufficient, we <strong>automatically convert your crypto (USDT today) into the settlement currency</strong> to cover the transaction. This crypto-to-fiat conversion is <strong>card-only and automatic</strong> — you cannot trigger it manually through the Exchange.</p>

<h2>3. Fees</h2>
<p>A per-transaction card fee of approximately <strong>1%</strong> applies to card spending. Any conversion uses the exchange effective rate. Fees are illustrative defaults and may be updated from time to time; applicable amounts are reflected in your balance and transaction history.</p>

<h2>4. Insufficient Funds and Declines</h2>
<p>If your balance cannot cover a transaction across your available funding sources, the transaction is declined and you will see <strong>"Insufficient balance."</strong> Declines may also occur where a card control (below) blocks the transaction.</p>

<h2>5. Card Controls</h2>
<p>You have real-time control over your card:</p>
<ul>
<li><strong>Freeze / unfreeze</strong> the card instantly.</li>
<li>Set a <strong>per-transaction limit</strong> and a <strong>daily limit</strong>.</li>
<li>Toggle spending <strong>channels</strong> (online, ATM, contactless).</li>
<li>Maintain an <strong>allowed-countries</strong> list.</li>
<li>Maintain a <strong>blocked merchant-category (MCC)</strong> list.</li>
<li><strong>Replace</strong> the card — the old card is immediately deactivated and a new one issued.</li>
</ul>

<h2>6. Refunds</h2>
<p>Merchant refunds return funds to your PaishaPay <strong>balance</strong> in the settlement currency. Refunds are <strong>merchant-initiated only</strong>. See our <a href="/pages/refund-policy">Refund Policy</a> for how refunds are processed across products.</p>

<h2>7. No Chargebacks</h2>
<p><strong>There are no chargebacks.</strong> PaishaPay card settlements do not carry a card-scheme chargeback or dispute-reversal mechanism. If you believe a charge is incorrect, the resolution route is a merchant-initiated refund. For unauthorized transactions, report immediately as described in our <a href="/pages/chargeback-policy">Chargeback Policy</a> and <a href="/pages/security">Security</a> notice.</p>

<h2>8. Your Responsibilities</h2>
<p>You are responsible for keeping your card details and account secure, for the transactions you authorize, and for using card controls that fit your risk appetite. Report a lost, compromised, or misused card promptly so it can be frozen or replaced.</p>

<h2>9. Suspension</h2>
<p>We may freeze or deactivate a card where required by law, risk, or a breach of the <a href="/pages/terms">Terms of Service</a> or <a href="/pages/acceptable-use">Acceptable Use Policy</a>.</p>

<h2>10. Contact</h2>
<p>For card questions, contact <strong>support@poisapay.com</strong>. This Agreement should be read together with the <a href="/pages/exchange-terms">Exchange Terms</a> and <a href="/pages/risk-disclosure">Risk Disclosure</a>.</p>
HTML,
    ],
    [
        'slug' => 'exchange-terms',
        'title' => 'Exchange Terms',
        'meta_description' => 'PaishaPay Exchange Terms — crypto-to-crypto swaps only, instant ledger settlement, transparent spread, and a 30-second quote lock.',
        'content' => <<<'HTML'
<p>These Exchange Terms govern conversions you make within the PaishaPay Exchange. They explain what pairs are supported, how pricing works, and how a quote is locked. They supplement the <a href="/pages/terms">Terms of Service</a> and <a href="/pages/wallet-agreement">Wallet Agreement</a>. By confirming a swap you accept these terms.</p>

<h2>1. Cryptocurrency-to-Cryptocurrency Only</h2>
<p>The user-facing Exchange supports <strong>cryptocurrency-to-cryptocurrency conversions only</strong>. Any attempt to swap a fiat pair — fiat-to-crypto, crypto-to-fiat, or fiat-to-fiat — is <strong>rejected</strong> with the exact message: <strong>"Only cryptocurrency-to-cryptocurrency exchanges are supported."</strong> Fiat balances exist for card spending, merchant payments, and settlement, but you cannot manually convert them in the Exchange.</p>

<h2>2. Instant Internal Settlement</h2>
<p>Swaps settle <strong>instantly on the internal ledger</strong>. There is no on-chain broadcast and no network confirmation wait — the debited asset leaves your balance and the credited asset arrives in the same atomic operation. Every swap is recorded as balanced ledger entries.</p>

<h2>3. Pricing: Market Rate Plus Spread</h2>
<p>The price you receive is the <strong>live market rate</strong> plus a transparent <strong>spread</strong> (an illustrative default of about <strong>0.75% / 75 basis points</strong>; some pairs may differ) and an optional platform fee (default zero). The spread is how PaishaPay is compensated for providing instant liquidity. The all-in rate and any fee are shown to you <strong>before you confirm</strong>.</p>

<h2>4. Quote Lock</h2>
<p>When you request a swap, PaishaPay presents a quote and <strong>locks the rate for approximately 30 seconds</strong>. If you confirm within that window, the swap executes at the locked rate. If the quote expires, you must request a fresh quote — the rate may have moved.</p>

<h2>5. Limits and KYC</h2>
<p>An optional <strong>daily swap limit</strong> and a <strong>minimum KYC tier</strong> may apply (default: none). Where a limit or verification requirement is in effect, it is enforced at the time of the swap. See our <a href="/pages/kyc-policy">KYC Policy</a> for verification tiers.</p>

<h2>6. Platform Liquidity</h2>
<p>Swaps trade against PaishaPay's own liquidity and dealer inventory. By confirming a swap, <strong>you accept the quoted rate</strong> as final for that transaction. If liquidity for a pair is temporarily unavailable, a swap may be declined and you can try again later.</p>

<h2>7. Volatility Risk</h2>
<p>Rates change continuously and can move sharply. The value of the asset you receive can rise or fall after the swap. Before trading, review our <a href="/pages/crypto-risk">Crypto Risk</a> and <a href="/pages/risk-disclosure">Risk Disclosure</a> statements. You are solely responsible for your swap decisions.</p>

<h2>8. Your Responsibilities</h2>
<p>Check the pair, the amount, and the quoted rate before confirming. Because settlement is instant and final, swaps cannot be reversed on request once executed. Any correction would require a separate, new swap at the prevailing rate.</p>

<h2>9. Contact</h2>
<p>For Exchange questions, contact <strong>support@poisapay.com</strong>. This document should be read together with the <a href="/pages/terms">Terms of Service</a> and <a href="/pages/limitation-of-liability">Limitation of Liability</a> statement.</p>
HTML,
    ],
    [
        'slug' => 'deposit-policy',
        'title' => 'Deposit Policy',
        'meta_description' => 'PaishaPay Deposit Policy — crypto on-chain deposits only, per-network addresses, confirmation counts, and correct-network warnings.',
        'content' => <<<'HTML'
<p>This Deposit Policy explains how you add funds to PaishaPay, how deposits are credited, and the precautions you must take. It supplements the <a href="/pages/wallet-agreement">Wallet Agreement</a> and <a href="/pages/terms">Terms of Service</a>. Please read it before making a deposit.</p>

<h2>1. Crypto On-Chain Deposits Only</h2>
<p>PaishaPay accepts <strong>crypto on-chain deposits only</strong>. There is <strong>no direct fiat or bank top-up</strong> rail. To hold a fiat-denominated balance you can convert supported crypto within the platform where available; you cannot wire or transfer fiat directly into PaishaPay.</p>

<h2>2. Per-Network Deposit Addresses</h2>
<p>You receive a <strong>deposit address per network</strong>. Because PaishaPay uses coin pooling, a coin credits to a single pooled balance regardless of the network you used to deposit it — but the address and network you send to must match the asset. Always deposit using the address shown for the specific network you intend to use.</p>

<h2>3. Network Confirmations</h2>
<p>Deposits are credited after the required number of on-chain confirmations. Indicative confirmation counts are:</p>
<ul>
<li><strong>Tron</strong> — 19 confirmations</li>
<li><strong>Ethereum</strong> — 12 confirmations</li>
<li><strong>BNB Smart Chain</strong> — 15 confirmations</li>
<li><strong>Polygon</strong> — 30 confirmations</li>
<li><strong>Arbitrum One, Optimism, Base</strong> — 20 confirmations each</li>
<li><strong>Avalanche C-Chain</strong> — 15 confirmations</li>
</ul>
<p>Confirmation requirements protect against chain reorganizations and may be adjusted for network conditions.</p>

<h2>4. Crediting and Fees</h2>
<p>Once confirmed, your balance is credited <strong>net of any configurable platform deposit fee</strong> (an illustrative default of about 1%). The exact fee is shown, and the net credited amount is reflected on your ledger. Minimum deposit amounts may apply for certain networks.</p>

<h2>5. Correct Asset and Correct Network — Important</h2>
<p><strong>Always send the correct asset on the correct network.</strong> Sending an unsupported asset, or using a network that does not match the address, can cause <strong>permanent and irrecoverable loss</strong>. Note that not every asset is available on every network — for example, USDC is not supported on Tron or Base, and USDT is not supported on Base. If you are unsure, verify the asset and network before sending. PaishaPay cannot recover misdirected deposits.</p>

<h2>6. Processing Times</h2>
<p>Deposit crediting depends on <strong>network congestion</strong> and block times. During periods of heavy network activity, confirmations — and therefore crediting — may take longer than usual. This is a property of the underlying blockchain and outside PaishaPay's control.</p>

<h2>7. Your Responsibilities</h2>
<p>You are responsible for using the correct address and network, for any minimums, and for the accuracy of the deposit you initiate from an external wallet or exchange. Keep a record of your deposit transaction hash so support can assist if needed.</p>

<h2>8. Related Policies and Contact</h2>
<p>See the <a href="/pages/withdrawal-policy">Withdrawal Policy</a> for moving funds out, and the <a href="/pages/risk-disclosure">Risk Disclosure</a> for on-chain risks. For deposit questions, contact <strong>support@poisapay.com</strong>.</p>
HTML,
    ],
    [
        'slug' => 'withdrawal-policy',
        'title' => 'Withdrawal Policy',
        'meta_description' => 'PaishaPay Withdrawal Policy — crypto and manual fiat cash-out, fees, KYC daily ceilings, review holds, and security controls.',
        'content' => <<<'HTML'
<p>This Withdrawal Policy explains how you move funds out of PaishaPay, the fees and limits that apply, the review process, and the security controls available to you. It supplements the <a href="/pages/wallet-agreement">Wallet Agreement</a>, <a href="/pages/kyc-policy">KYC Policy</a>, and <a href="/pages/terms">Terms of Service</a>.</p>

<h2>1. Withdrawal Methods</h2>
<p>You can make a <strong>crypto withdrawal</strong> to your own external address, and request a <strong>fiat cash-out</strong> (bank or mobile wallet). Fiat cash-out is <strong>processed and reviewed manually</strong> by the operator — it is not an instant automated bank rail — so it may take longer than a crypto withdrawal.</p>

<h2>2. Convert-Before-Withdraw</h2>
<p>You can withdraw a different asset than the one you currently hold by <strong>converting first</strong>. This runs as a <strong>single atomic money path</strong>: the conversion and the withdrawal are handled together, so you always see the resulting asset and amount before you confirm.</p>

<h2>3. Fees</h2>
<p>A small <strong>configurable percentage withdrawal fee</strong> applies (an illustrative default of about 1%). <strong>PaishaPay absorbs the on-chain gas cost</strong> — there is no separate per-network flat fee. All fees are shown before you confirm.</p>

<h2>4. Approval, Review, and Risk Scoring</h2>
<p>Smaller withdrawals (approximately up to $500) may <strong>auto-approve</strong>. Larger or higher-risk withdrawals are routed to <strong>manual review</strong>. Risk scoring considers factors such as the <strong>amount, velocity, account age, and whether the destination address is new</strong>. Review protects you and the platform against fraud and error.</p>

<h2>5. KYC Daily Withdrawal Ceilings</h2>
<p>Rolling 24-hour withdrawal ceilings apply by verification tier (operator-configurable):</p>
<ul>
<li><strong>Unverified</strong> — $0 (cannot withdraw)</li>
<li><strong>Basic</strong> — up to $1,000 per day</li>
<li><strong>Full</strong> — up to $25,000 per day</li>
</ul>
<p>To raise your ceiling, complete a higher verification tier as described in our <a href="/pages/kyc-policy">KYC Policy</a>.</p>

<h2>6. Security Controls</h2>
<p>You can enable optional controls to protect withdrawals:</p>
<ul>
<li>A <strong>withdrawal-address allow-list</strong>.</li>
<li>A <strong>cooldown</strong> (approximately 24 hours) before a newly added address can be used.</li>
<li>A <strong>daily withdrawal-count cap</strong> (approximately 10 per day).</li>
</ul>
<p>These controls reduce the impact of account compromise. See our <a href="/pages/security">Security</a> notice.</p>

<h2>7. Withdrawal States</h2>
<p>A withdrawal moves through the following states: <strong>pending → approved → broadcasting → completed</strong>. A withdrawal may instead enter <strong>review</strong>, or end as <strong>failed</strong> or <strong>cancelled</strong>. A crypto withdrawal is complete once it has been broadcast and settled on-chain; once on-chain it is <strong>irreversible</strong>.</p>

<h2>8. Why a Withdrawal May Be Held</h2>
<p>A withdrawal may be pending or held for reasons including: manual review of a larger or higher-risk request, an address still within its cooldown, a hit against the daily count cap or KYC ceiling, an AML or sanctions check, or a temporary network issue. We aim to resolve reviews promptly and may contact you for additional information under our <a href="/pages/aml-policy">AML Policy</a>.</p>

<h2>9. Your Responsibilities</h2>
<p>You must ensure the destination address and network are correct. As with deposits, funds sent to the wrong network or an unsupported asset may be <strong>permanently lost</strong>. Double-check every withdrawal before you confirm.</p>

<h2>10. Contact</h2>
<p>For withdrawal questions, contact <strong>support@poisapay.com</strong>. This policy should be read with the <a href="/pages/deposit-policy">Deposit Policy</a> and <a href="/pages/risk-disclosure">Risk Disclosure</a>.</p>
HTML,
    ],
    [
        'slug' => 'refund-policy',
        'title' => 'Refund Policy',
        'meta_description' => 'PaishaPay Refund Policy — how card, merchant-invoice, and Shop-order refunds work, and why on-chain crypto transfers are irreversible.',
        'content' => <<<'HTML'
<p>This Refund Policy explains how refunds work across PaishaPay's products — card transactions, merchant invoices, and Shop orders — and the important difference between platform refunds and irreversible on-chain transfers. It supplements the <a href="/pages/terms">Terms of Service</a>.</p>

<h2>1. Overview</h2>
<p>PaishaPay refunds are posted as <strong>reversing ledger entries</strong>: value is returned to the appropriate balance, and no ledger record is deleted. How a refund is initiated and where funds land depends on the product involved.</p>

<h2>2. Card Refunds</h2>
<p>Card refunds are <strong>merchant-initiated</strong> and may be <strong>full or partial</strong>. Refunded funds return to <strong>your PaishaPay balance</strong> in the settlement currency. PaishaPay does not offer chargebacks — refunds on card transactions come from the merchant. See the <a href="/pages/cardholder-agreement">Cardholder Agreement</a> and <a href="/pages/chargeback-policy">Chargeback Policy</a>.</p>

<h2>3. Merchant Invoice Refunds</h2>
<p>When a merchant refunds an invoice you paid, a <strong>full refund returns the net amount plus the processing fee to you, the payer</strong>. The refund settles to your PaishaPay balance. Merchant refunds are initiated by the merchant; PaishaPay processes the reversal on the ledger.</p>

<h2>4. Shop Order Refunds</h2>
<p>For a Shop order, a refund <strong>reverses the associated ledger entries</strong> and, where the order included digital goods, <strong>revokes access</strong> to those goods. This ensures the buyer is made whole while access to delivered digital content is withdrawn.</p>

<h2>5. Crypto Transfers Are Irreversible</h2>
<p>Refunds apply to PaishaPay platform transactions. A <strong>crypto transfer that has settled on-chain cannot be reversed</strong> — once broadcast to the blockchain, it is final. If you sent crypto to the wrong address or network, PaishaPay cannot recover it. Always verify details before withdrawing or sending, as described in our <a href="/pages/withdrawal-policy">Withdrawal Policy</a>.</p>

<h2>6. How to Request a Refund</h2>
<p>Because card and merchant refunds are merchant-initiated, the first step is usually to contact the merchant or seller directly through your order or transaction record. If you cannot reach the merchant, or you believe a transaction was unauthorized, raise a support ticket with <strong>support@poisapay.com</strong> and include the transaction reference. For P2P trades, dispute resolution is handled separately by admins with evidence.</p>

<h2>7. Processing and Timing</h2>
<p>Once a refund is initiated, the reversing entries settle to the relevant balance. Timing depends on the product and, for merchant refunds, on when the merchant initiates the reversal. You will see the returned amount reflected in your balance and transaction history.</p>

<h2>8. Your Responsibilities</h2>
<p>Keep your transaction references and any communication with merchants. Review purchases carefully before paying, since on-chain movements and completed swaps cannot be reversed on request.</p>

<h2>9. Related Policies and Contact</h2>
<p>See the <a href="/pages/chargeback-policy">Chargeback Policy</a> for how disputes are handled and the <a href="/pages/complaint-policy">Complaint Policy</a> for escalation. For refund questions, contact <strong>support@poisapay.com</strong>.</p>
HTML,
    ],
    [
        'slug' => 'chargeback-policy',
        'title' => 'Chargeback Policy',
        'meta_description' => 'PaishaPay Chargeback Policy — why crypto settlements are final, how disputes are resolved instead, and how to report unauthorized transactions.',
        'content' => <<<'HTML'
<p>This Chargeback Policy explains why PaishaPay does not offer chargebacks, how disputes are handled instead, and how to report an unauthorized transaction. It supplements the <a href="/pages/cardholder-agreement">Cardholder Agreement</a>, <a href="/pages/refund-policy">Refund Policy</a>, and <a href="/pages/terms">Terms of Service</a>.</p>

<h2>1. Crypto Settlements Are Final</h2>
<p>PaishaPay settles value on an immutable double-entry ledger and, for on-chain movements, on public blockchains. Once a transaction settles it is <strong>final</strong>. There is <strong>no chargeback or card-scheme dispute-reversal mechanism</strong> on the platform. This is a fundamental property of how digital-asset settlement works and applies to card spending, merchant payments, swaps, and withdrawals.</p>

<h2>2. What "No Chargebacks" Means</h2>
<p>You cannot force-reverse a completed transaction by filing a scheme dispute. Instead, corrections happen through <strong>refunds initiated by the counterparty</strong> (a merchant or seller) or, for peer trades, through <strong>administrative dispute resolution</strong>. Please factor this into how you transact — especially with merchants you do not know.</p>

<h2>3. How Disputes Are Handled Instead</h2>
<p>PaishaPay provides the following routes to resolve issues:</p>
<ul>
<li><strong>Merchant refunds</strong> — for card and invoice payments, the merchant can issue a full or partial refund back to your balance, as set out in our <a href="/pages/refund-policy">Refund Policy</a>.</li>
<li><strong>P2P admin dispute resolution</strong> — for peer-to-peer trades, either party can raise a dispute with evidence; an administrator reviews it and decides to <strong>release or refund the escrow</strong> held on the ledger.</li>
<li><strong>Support tickets</strong> — for other issues, our team can investigate and help coordinate a resolution where appropriate.</li>
</ul>

<h2>4. Reporting Unauthorized Transactions</h2>
<p>If you see a transaction you did not authorize, act quickly:</p>
<ol>
<li><strong>Freeze</strong> the affected card immediately from your controls, if a card is involved.</li>
<li><strong>Change your password</strong> and review your active devices and two-factor settings.</li>
<li><strong>Report</strong> the transaction to <strong>support@poisapay.com</strong> with the reference and details.</li>
</ol>
<p>We will investigate promptly. Suspicious activity may trigger a review or an account freeze under our <a href="/pages/aml-policy">AML Policy</a> to protect your funds. See also our <a href="/pages/security">Security</a> notice for protecting your account.</p>

<h2>5. Your Responsibilities</h2>
<p>Because settlements are final, verify the recipient, amount, and details before you confirm any payment, swap, or withdrawal. Keep your credentials, recovery codes, and card controls secure, and enable optional protections such as two-factor authentication and withdrawal address allow-listing.</p>

<h2>6. Escalation and Contact</h2>
<p>If you are unhappy with the outcome of a dispute, you can escalate through our <a href="/pages/complaint-policy">Complaint Policy</a>. For unauthorized-transaction reports and dispute questions, contact <strong>support@poisapay.com</strong>.</p>
HTML,
    ],
];
