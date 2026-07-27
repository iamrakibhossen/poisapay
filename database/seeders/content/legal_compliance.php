<?php

declare(strict_types=1);

return [
    [
        'slug' => 'privacy',
        'title' => 'Privacy Policy',
        'meta_description' => 'How PaishaPay collects, uses, protects, shares, and retains your personal data, and the rights you have over it.',
        'content' => <<<'HTML'
<p>This Privacy Policy explains how PaishaPay collects, uses, shares, protects, and retains your personal information when you use our custodial digital-asset wallet and payments platform. PaishaPay is a financial service, so handling your data responsibly is central to how we operate. Please read this policy alongside our <a href="/pages/cookie-policy">Cookie Policy</a>, <a href="/pages/aml-policy">AML Policy</a>, <a href="/pages/kyc-policy">KYC Policy</a>, and <a href="/pages/electronic-communication-consent">Electronic Communication Consent</a>.</p>

<h2>1. Introduction and Purpose</h2>
<p>PaishaPay provides a USD and crypto wallet, an internal exchange, virtual cards, merchant payments, a peer-to-peer marketplace, and a commerce module. To deliver these services securely and to meet our legal and regulatory obligations, we must collect and process certain personal data. This policy tells you what we collect, why we collect it, who we share it with, how long we keep it, and how you can exercise your rights.</p>

<h2>2. Information We Collect</h2>
<p>We collect the following categories of information:</p>
<ul>
<li><strong>Registration details:</strong> your name, email address, phone number (where provided), and the credentials used to secure your account.</li>
<li><strong>Identity verification (KYC) data:</strong> government-issued identity documents, proof of address where required, and liveness or selfie images used to confirm you are who you say you are. This is described further in our <a href="/pages/kyc-policy">KYC Policy</a>.</li>
<li><strong>Transaction and ledger data:</strong> your deposits, withdrawals, swaps, card activity, merchant payments, peer-to-peer trades, balances, and the immutable double-entry ledger entries that record every money movement on your account.</li>
<li><strong>Device, log, and technical data:</strong> IP address, device fingerprint, browser and operating-system details, session and authentication events, and security logs generated when you use the platform.</li>
</ul>

<h2>3. How and Why We Use Your Information</h2>
<p>We process your information for the following purposes:</p>
<ul>
<li><strong>Operating your account:</strong> creating and maintaining your wallet, authenticating you, and providing customer support.</li>
<li><strong>Processing transactions:</strong> executing deposits, withdrawals, swaps, card authorizations and settlements, merchant payments, and P2P escrow, and recording them accurately on the ledger.</li>
<li><strong>Anti-money-laundering and fraud prevention:</strong> screening, transaction monitoring, risk scoring, and investigating suspicious activity, as set out in our <a href="/pages/aml-policy">AML Policy</a>.</li>
<li><strong>Improving our services:</strong> understanding how features are used so we can make the platform more reliable, secure, and useful.</li>
<li><strong>Meeting legal obligations:</strong> complying with applicable financial-crime, sanctions, tax, record-keeping, and reporting requirements.</li>
</ul>

<h2>4. How We Share Information</h2>
<p>We do <strong>not</strong> sell your personal data. We share it only where lawful and necessary:</p>
<ul>
<li><strong>Service providers:</strong> vetted partners who help us operate the platform, such as identity-verification, card-issuing, cloud-hosting, communications, and analytics providers, bound by confidentiality and data-protection obligations.</li>
<li><strong>Authorities:</strong> regulators, law-enforcement, and competent authorities where we are legally required or permitted to disclose, including for sanctions, AML, and suspicious-activity reporting.</li>
</ul>

<h2>5. How We Protect Your Information</h2>
<p>We apply layered safeguards including encryption of sensitive data, strict access controls, continuous monitoring, tamper-evident audit logging, and device and session security. No system is perfectly secure, so you must also protect your account by keeping your credentials confidential, enabling two-factor authentication, and reporting anything suspicious.</p>

<h2>6. Data Retention</h2>
<p>We retain your information for as long as your account is active and for a further period afterwards to meet legal, regulatory, accounting, and dispute-resolution requirements. Ledger and financial records are kept for the periods required by applicable law. When data is no longer needed, we delete or anonymise it.</p>

<h2>7. Your Rights</h2>
<p>Subject to applicable law and the limits below, you may request access to the personal data we hold about you, ask us to correct inaccurate data, and request deletion of your data. Because we are a regulated financial service, some rights are limited: we cannot delete records we are legally required to keep, and we may need to verify your identity before acting on a request. To exercise a right, contact us at <a href="mailto:support@poisapay.com">support@poisapay.com</a>.</p>

<h2>8. Important Notices</h2>
<p>This policy may be updated from time to time; the current version always governs. Governing law is the law of the jurisdiction in which PaishaPay is established and operates; the operator sets the specific jurisdiction and venue. If you do not agree with how we handle your data, you should not use the platform.</p>

<h2>9. Contact</h2>
<p>For any privacy question or request, contact <a href="mailto:support@poisapay.com">support@poisapay.com</a>. For compliance matters you may also reach <a href="mailto:compliance@poisapay.com">compliance@poisapay.com</a>.</p>
HTML,
    ],
    [
        'slug' => 'cookie-policy',
        'title' => 'Cookie Policy',
        'meta_description' => 'How PaishaPay uses cookies and local storage for sessions, security, preferences, and analytics, and how you can manage them.',
        'content' => <<<'HTML'
<p>This Cookie Policy explains how PaishaPay uses cookies and similar local-storage technologies when you use our website and application. It describes what these technologies do, why we rely on them, and how you can manage them. It supplements our <a href="/pages/privacy">Privacy Policy</a>.</p>

<h2>1. Introduction and Purpose</h2>
<p>Cookies are small text files stored on your device, and local storage is a similar mechanism your browser uses to hold data. PaishaPay uses these technologies to keep you securely signed in, protect your account, remember your preferences, and understand how the platform is used. Our consumer application is <strong>server-rendered</strong>, meaning most pages are produced on our servers and delivered to your browser, so cookies are used primarily for session security and preferences rather than heavy client-side tracking.</p>

<h2>2. Types of Cookies and Storage We Use</h2>
<ul>
<li><strong>Essential session, authentication, and CSRF cookies:</strong> these keep you logged in as you move between pages and protect forms against cross-site request forgery. They are strictly necessary for the platform to function.</li>
<li><strong>Security cookies:</strong> these support protective controls such as Google reCAPTCHA on sensitive forms and help us detect and prevent abuse and fraud.</li>
<li><strong>Preference storage:</strong> we remember your choices, such as your base display currency (for example USD, BDT, or EUR) and your interface language (English or Bangla).</li>
<li><strong>Analytics:</strong> where enabled, we use limited analytics to understand aggregate usage and improve the service.</li>
</ul>

<h2>3. Why Essential Cookies Matter</h2>
<p>Essential cookies are required to authenticate you and secure your session. If you block or disable them, core functions will break: you will not be able to log in, and money-moving actions will not work. For this reason, essential cookies cannot be switched off through in-app settings while you use secured areas of the platform.</p>

<h2>4. Managing Cookies</h2>
<p>You can view, delete, and block cookies through your browser settings. Most browsers let you clear stored cookies and local storage, and choose whether to accept cookies from specific sites. Please note that disabling essential cookies will prevent you from signing in and using PaishaPay. Managing non-essential cookies where offered will not affect your ability to log in.</p>

<h2>5. Your Responsibilities</h2>
<p>Keep the device and browser you use to access PaishaPay secure. Clearing cookies will sign you out and reset saved preferences. If you use a shared or public device, log out and clear stored data when you finish.</p>

<h2>6. Important Notices</h2>
<p>We may update this Cookie Policy from time to time as our technology and legal obligations change. For how the data collected through cookies is used, shared, and retained, please read our <a href="/pages/privacy">Privacy Policy</a>. Questions can be sent to <a href="mailto:support@poisapay.com">support@poisapay.com</a>.</p>
HTML,
    ],
    [
        'slug' => 'acceptable-use',
        'title' => 'Acceptable Use Policy',
        'meta_description' => 'The activities prohibited on PaishaPay and the consequences of misuse, including limits, holds, freezes, and account closure.',
        'content' => <<<'HTML'
<p>This Acceptable Use Policy sets out how you may and may not use PaishaPay. It exists to keep the platform safe, lawful, and available for everyone, and to protect the integrity of our financial systems. It works alongside our <a href="/pages/terms">Terms of Service</a>, <a href="/pages/aml-policy">AML Policy</a>, <a href="/pages/sanctions-compliance">Sanctions Compliance</a> statement, and <a href="/pages/termination-policy">Termination Policy</a>.</p>

<h2>1. Introduction and Purpose</h2>
<p>By using PaishaPay you agree to use the platform only for lawful purposes and in a way that does not harm us, other users, or third parties. This policy lists activities that are strictly prohibited and explains what happens if you engage in them.</p>

<h2>2. Prohibited Activities</h2>
<p>You must not use PaishaPay to engage in, facilitate, or attempt any of the following:</p>
<ul>
<li><strong>Money laundering</strong> or moving the proceeds of crime.</li>
<li><strong>Terrorist financing</strong> or funding of proscribed organisations.</li>
<li><strong>Fraud</strong> of any kind, including deception, impersonation, or misrepresentation.</li>
<li><strong>Sanctions evasion</strong>, or transacting with sanctioned persons, entities, or jurisdictions.</li>
<li><strong>Trade in illegal goods or services</strong>, or any transaction prohibited by applicable law.</li>
<li><strong>Market abuse</strong>, including manipulation of prices, wash trading, or exploiting the exchange or P2P marketplace.</li>
<li><strong>Unauthorized access</strong> to systems, accounts, or data that do not belong to you.</li>
<li><strong>Scraping or abuse of our services or programmatic interfaces</strong>, including excessive automated requests that degrade the platform.</li>
<li><strong>Multiple-account abuse</strong>, such as creating additional accounts to evade limits, verification, or enforcement.</li>
<li><strong>Refund abuse</strong>, including attempting to reverse legitimately settled payments in bad faith. Note that PaishaPay does not support card chargebacks; refunds are merchant-initiated only.</li>
<li><strong>Using another person's identity</strong> or verification documents, or letting someone else use your verified account.</li>
</ul>

<h2>3. Your Responsibilities</h2>
<p>You are responsible for all activity on your account. Keep your credentials secure, enable two-factor authentication, provide accurate information, and use the platform honestly. If you become aware of misuse, unauthorized access, or suspicious activity involving your account, notify us immediately at <a href="mailto:support@poisapay.com">support@poisapay.com</a>.</p>

<h2>4. Consequences of Misuse</h2>
<p>Where we reasonably believe this policy has been breached, or where required for legal, regulatory, or security reasons, we may take one or more of the following actions:</p>
<ul>
<li>Apply <strong>limits</strong> to your account activity.</li>
<li>Place <strong>holds</strong> on specific transactions or balances.</li>
<li><strong>Freeze</strong> your account, blocking all value movement, on a manual or risk-triggered basis.</li>
<li><strong>Close</strong> your account in accordance with our <a href="/pages/termination-policy">Termination Policy</a>.</li>
<li><strong>Report</strong> activity to regulators, law-enforcement, or other competent authorities where legally required or permitted.</li>
</ul>

<h2>5. Important Notices</h2>
<p>We may update this policy from time to time to reflect new risks and legal requirements. Enforcement decisions are made in good faith and in line with our compliance obligations. Governing law is the law of the jurisdiction in which PaishaPay is established and operates; the operator sets the specific jurisdiction and venue. Questions about acceptable use can be sent to <a href="mailto:compliance@poisapay.com">compliance@poisapay.com</a>.</p>
HTML,
    ],
    [
        'slug' => 'aml-policy',
        'title' => 'AML Policy',
        'meta_description' => 'PaishaPay\'s risk-based Anti-Money-Laundering and Counter-Financing-of-Terrorism programme, including monitoring, screening, and reporting.',
        'content' => <<<'HTML'
<p>This Anti-Money-Laundering (AML) Policy describes PaishaPay's programme for preventing, detecting, and reporting money laundering and the financing of terrorism (CFT). As a custodial digital-asset and payments platform, we operate a risk-based compliance programme and cooperate with regulators and law-enforcement. This policy should be read with our <a href="/pages/kyc-policy">KYC Policy</a> and <a href="/pages/sanctions-compliance">Sanctions Compliance</a> statement.</p>

<h2>1. Introduction and Purpose</h2>
<p>PaishaPay is committed to preventing its services from being used to launder money, finance terrorism, or evade sanctions. Our programme is designed to identify and manage financial-crime risk across every money path — deposits, withdrawals, swaps, cards, merchant payments, and the P2P marketplace.</p>

<h2>2. Risk-Based Approach</h2>
<p>We assess and manage risk based on factors such as customer profile, product usage, transaction patterns, and jurisdiction. Higher-risk situations receive stronger controls, and lower-risk situations are handled proportionately. This risk-based approach guides how we verify customers, monitor activity, and escalate concerns.</p>

<h2>3. Customer Due Diligence and Enhanced Due Diligence</h2>
<p>We perform Customer Due Diligence (CDD) by verifying identity through our KYC process before you can access certain features, and we apply Enhanced Due Diligence (EDD) where risk is elevated. This can include additional documentation, source-of-funds enquiries, and closer ongoing review. Details of the tiers and documents are in our <a href="/pages/kyc-policy">KYC Policy</a>.</p>

<h2>4. Transaction Monitoring</h2>
<p>We monitor activity across the platform using risk scoring that considers factors such as amount, velocity, account age, and new destination addresses. Unusual or higher-risk transactions may be delayed for manual review, and larger withdrawals are reviewed before they are processed. An account freeze — applied manually or triggered by risk — blocks all value movement while a matter is investigated.</p>

<h2>5. Sanctions and Watchlist Screening</h2>
<p>We screen customers and activity against applicable sanctions and watchlists. We refuse service to sanctioned persons, entities, and jurisdictions, and we rescreen on an ongoing basis. Full details are in our <a href="/pages/sanctions-compliance">Sanctions Compliance</a> statement.</p>

<h2>6. Politically Exposed and High-Risk Persons</h2>
<p>We apply additional scrutiny to politically exposed persons (PEPs) and other higher-risk customers, including enhanced review and, where appropriate, senior sign-off, consistent with our risk-based approach.</p>

<h2>7. Suspicious-Activity Reporting</h2>
<p>Where we identify activity that may indicate money laundering, terrorist financing, or other financial crime, we report it to competent authorities where legally required. We do not tip off customers where the law prohibits it. We maintain compliance case management to record and resolve alerts.</p>

<h2>8. Travel Rule and Record Keeping</h2>
<p>Where applicable, we capture and transmit originator and beneficiary information for transfers above the relevant threshold (approximately $1,000), consistent with the Travel Rule. We keep records of identification, transactions, and compliance decisions for the periods required by applicable law.</p>

<h2>9. Governance, Staff, and Cooperation</h2>
<p>Our programme is supported by appropriate governance, staff awareness, and oversight. We cooperate with regulators and law-enforcement and respond to lawful requests. Governing law is the law of the jurisdiction in which PaishaPay is established and operates; the operator sets the specific jurisdiction and venue.</p>

<h2>10. Your Responsibilities</h2>
<p>You must provide accurate information, complete verification when asked, and use PaishaPay only for lawful purposes as set out in our <a href="/pages/acceptable-use">Acceptable Use Policy</a>. Concerns or questions can be directed to <a href="mailto:compliance@poisapay.com">compliance@poisapay.com</a>.</p>
HTML,
    ],
    [
        'slug' => 'kyc-policy',
        'title' => 'KYC Policy',
        'meta_description' => 'PaishaPay\'s Know-Your-Customer verification: tiers, withdrawal ceilings, documents collected, re-verification, and appeals.',
        'content' => <<<'HTML'
<p>This Know-Your-Customer (KYC) Policy explains how PaishaPay verifies the identity of its users, what each verification tier unlocks, and how your verification data is handled. Identity verification is a legal requirement for a regulated financial service and a core part of our anti-financial-crime controls. Read this alongside our <a href="/pages/aml-policy">AML Policy</a> and <a href="/pages/privacy">Privacy Policy</a>.</p>

<h2>1. Introduction and Purpose</h2>
<p>To open access to money-moving features, PaishaPay must confirm who you are. Verification protects you and the platform against fraud, impersonation, and financial crime, and it lets us apply the right limits to your account.</p>

<h2>2. Verification Tiers</h2>
<p>PaishaPay uses three tiers. Withdrawal ceilings are daily (rolling 24-hour) and are operator-configurable; the figures below are illustrative and shown to you in-app:</p>
<ul>
<li><strong>Unverified:</strong> you can deposit crypto, use the exchange, and browse the platform, but you <strong>cannot withdraw</strong> (daily ceiling of $0) and cannot issue cards.</li>
<li><strong>Basic:</strong> you can withdraw up to approximately <strong>$1,000 per day</strong>.</li>
<li><strong>Full:</strong> you can withdraw up to approximately <strong>$25,000 per day</strong>, <strong>issue virtual cards</strong>, and receive the highest level of access.</li>
</ul>

<h2>3. Documents and Checks</h2>
<p>To progress through the tiers we may collect and verify:</p>
<ul>
<li>A <strong>government-issued identity document</strong>.</li>
<li><strong>Proof of address</strong>, where required.</li>
<li>A <strong>liveness or selfie check</strong> to confirm the document belongs to you.</li>
</ul>
<p>Card issuance requires <strong>Full</strong> verification.</p>

<h2>4. Re-verification and Source of Funds</h2>
<p>We may ask you to re-verify your identity, refresh your details, or provide information about the source of your funds. These requests support ongoing due diligence and help us keep your account compliant and secure. Failing to complete a required check may limit or suspend your access.</p>

<h2>5. Rejections and Appeals</h2>
<p>A verification attempt may be rejected for reasons such as an unreadable, expired, or mismatched document, a failed liveness check, information that does not match our records, or a risk or compliance concern. If your verification is rejected and you believe this is an error, you may appeal by contacting <a href="mailto:support@poisapay.com">support@poisapay.com</a> with the requested details.</p>

<h2>6. How Your Data Is Handled</h2>
<p>Verification data — including identity documents and selfie images — is handled in accordance with our <a href="/pages/privacy">Privacy Policy</a>, using encryption, access controls, and retention periods required by law. We do not sell this data and share it only with vetted verification providers and, where legally required, competent authorities.</p>

<h2>7. Your Responsibilities</h2>
<p>Provide genuine, current, and accurate documents that belong to you. Do not use another person's identity or documents, and do not let anyone else use your verified account. Misuse may lead to limits, holds, freezes, or account closure under our <a href="/pages/acceptable-use">Acceptable Use Policy</a>.</p>

<h2>8. Important Notices</h2>
<p>Ceilings, tiers, and required documents may be updated from time to time to meet legal and risk requirements. Governing law is the law of the jurisdiction in which PaishaPay is established and operates; the operator sets the specific jurisdiction and venue.</p>
HTML,
    ],
    [
        'slug' => 'sanctions-compliance',
        'title' => 'Sanctions Compliance',
        'meta_description' => 'PaishaPay screens against sanctions and watchlists and does not serve sanctioned persons, entities, or high-risk jurisdictions.',
        'content' => <<<'HTML'
<p>This Sanctions Compliance statement explains how PaishaPay screens for and responds to sanctions risk. Complying with sanctions is a legal obligation and a core control in our financial-crime programme. It should be read with our <a href="/pages/aml-policy">AML Policy</a> and <a href="/pages/acceptable-use">Acceptable Use Policy</a>.</p>

<h2>1. Introduction and Purpose</h2>
<p>PaishaPay must not be used by, or for the benefit of, sanctioned persons, entities, or jurisdictions. This statement describes the screening we perform, the restrictions we apply, and your obligations as a user.</p>

<h2>2. Screening</h2>
<p>We screen customers, counterparties, and activity against applicable sanctions and watchlists as part of onboarding and on an ongoing basis. Screening is integrated into our risk-based monitoring so that matches can be identified, reviewed, and acted upon promptly.</p>

<h2>3. Restricted Persons and Jurisdictions</h2>
<p>We do not provide services to persons or entities that are subject to applicable sanctions, or to comprehensively sanctioned or high-risk jurisdictions. High-risk jurisdictions include, without limitation, <strong>North Korea, Iran, Syria, and Cuba</strong>. We may also restrict service based on other risk factors as our obligations evolve.</p>

<h2>4. Ongoing Rescreening</h2>
<p>Sanctions lists change frequently. We rescreen customers and activity on an ongoing basis, so an account that was previously in good standing may be restricted if a new match or risk arises.</p>

<h2>5. Actions We Take</h2>
<p>Where a sanctions concern is identified, we may:</p>
<ul>
<li><strong>Block or reject</strong> transactions.</li>
<li><strong>Freeze</strong> an account, halting all value movement.</li>
<li><strong>Report</strong> to competent authorities where legally required.</li>
</ul>
<p>We take these actions to comply with the law and cannot waive them.</p>

<h2>6. Your Responsibilities</h2>
<p>By using PaishaPay you represent that you are not a sanctioned person, are not owned or controlled by a sanctioned party, are not located in a restricted jurisdiction, and are not acting on behalf of any sanctioned person or entity. You must not use PaishaPay to facilitate sanctions evasion. Breaching these obligations is prohibited under our <a href="/pages/acceptable-use">Acceptable Use Policy</a> and may lead to account closure and reporting.</p>

<h2>7. Important Notices</h2>
<p>This statement may be updated as sanctions regimes and our legal obligations change. Governing law is the law of the jurisdiction in which PaishaPay is established and operates; the operator sets the specific jurisdiction and venue. Sanctions questions can be directed to <a href="mailto:compliance@poisapay.com">compliance@poisapay.com</a>.</p>
HTML,
    ],
    [
        'slug' => 'risk-disclosure',
        'title' => 'Risk Disclosure',
        'meta_description' => 'General risks of using PaishaPay, including digital-asset volatility, custodial and operational risk, and the absence of insurance.',
        'content' => <<<'HTML'
<p>This Risk Disclosure summarises the general risks of using the PaishaPay platform. You should read and understand it before using our services. Digital assets and financial services carry risk, and by using PaishaPay you accept these risks. This statement should be read with our <a href="/pages/crypto-risk">Crypto Risk Notice</a>, <a href="/pages/exchange-terms">Exchange Terms</a>, and <a href="/pages/wallet-agreement">Wallet Agreement</a>.</p>

<h2>1. Introduction and Purpose</h2>
<p>PaishaPay is a custodial digital-asset wallet and payments platform. It is not a bank, it is not an investment adviser, and nothing on the platform is investment advice. The purpose of this disclosure is to make the key risks clear so you can make informed decisions.</p>

<h2>2. Key Risks</h2>
<ul>
<li><strong>Price volatility:</strong> the value of digital assets can move sharply and unpredictably, and you may lose value.</li>
<li><strong>No insurance and not a bank:</strong> PaishaPay is not a bank and your balances are not covered by any deposit-insurance scheme. Funds are held custodially.</li>
<li><strong>Liquidity risk:</strong> at times it may be difficult to convert or move assets promptly, including where platform liquidity is limited.</li>
<li><strong>Technology and network risk:</strong> blockchains, smart contracts, and supporting systems can fail, be congested, or contain defects that affect transactions.</li>
<li><strong>Custodial and operational risk:</strong> because we hold assets on your behalf, you rely on our custody, controls, and continued operation. While the majority of crypto is held in offline cold storage with continuous reconciliation, no custody arrangement is risk-free.</li>
<li><strong>Regulatory and legal change:</strong> laws and regulations affecting digital assets can change and may restrict, delay, or prohibit certain services.</li>
<li><strong>Third-party and issuing-partner risk:</strong> some features depend on external providers, such as card-issuing partners, whose performance is outside our full control.</li>
<li><strong>Market and FX risk on conversions:</strong> where balances are converted, the applicable rate and spread affect the amount you receive.</li>
<li><strong>Irreversibility of on-chain transfers:</strong> once a crypto withdrawal is broadcast to a network it generally cannot be reversed or recovered.</li>
<li><strong>No investment advice:</strong> we do not advise you on whether to buy, hold, or sell any asset. Decisions are yours alone.</li>
</ul>

<h2>3. Your Acceptance of Risk</h2>
<p>By using PaishaPay you acknowledge these risks and accept responsibility for your decisions. Only transact with funds you can afford to lose, and take care to send the correct asset on the correct network.</p>

<h2>4. Important Notices</h2>
<p>This disclosure is general and not exhaustive; other risks may apply to your circumstances. For crypto-specific warnings see the <a href="/pages/crypto-risk">Crypto Risk Notice</a>. Governing law is the law of the jurisdiction in which PaishaPay is established and operates; the operator sets the specific jurisdiction and venue. Questions can be sent to <a href="mailto:support@poisapay.com">support@poisapay.com</a>.</p>
HTML,
    ],
    [
        'slug' => 'crypto-risk',
        'title' => 'Crypto Risk Notice',
        'meta_description' => 'Focused warning on crypto risks: stablecoin de-pegging, irreversible and wrong-network transfers, fees, forks, and possible total loss.',
        'content' => <<<'HTML'
<p>This Crypto Risk Notice highlights risks that are specific to holding and moving cryptocurrency on PaishaPay. It complements the broader <a href="/pages/risk-disclosure">Risk Disclosure</a> and should be read before you deposit, swap, or withdraw crypto. See also our <a href="/pages/deposit-policy">Deposit Policy</a> and <a href="/pages/withdrawal-policy">Withdrawal Policy</a>.</p>

<h2>1. Introduction and Purpose</h2>
<p>Cryptocurrency behaves differently from traditional money. This notice sets out the crypto-specific dangers you should understand so you can use PaishaPay carefully.</p>

<h2>2. Specific Crypto Risks</h2>
<ul>
<li><strong>Stablecoins can de-peg:</strong> assets such as USDT and USDC are designed to track a reference value, but that peg is not guaranteed and their value can deviate.</li>
<li><strong>Value can fall to zero:</strong> any digital asset can lose all of its value.</li>
<li><strong>Transactions are irreversible:</strong> once a transfer is confirmed on-chain it generally cannot be reversed. If you send to the wrong address, or use the wrong network, your funds can be permanently lost. Always send the correct asset on the correct network.</li>
<li><strong>Network congestion and fees:</strong> blockchains can become congested, causing delays and higher network fees, and confirmation times vary by network.</li>
<li><strong>Forks and protocol changes:</strong> a blockchain may fork or change its rules, which can affect the availability, value, or handling of an asset.</li>
<li><strong>No guaranteed buyback:</strong> PaishaPay does not guarantee to buy back, redeem, or convert any asset at a particular price or at all.</li>
<li><strong>Past performance is not indicative:</strong> previous price movements do not predict future results.</li>
</ul>

<h2>3. Use Only Funds You Can Afford to Lose</h2>
<p>Because losses can be sudden, total, and irreversible, you should only use funds you can afford to lose. Double-check the network and address before any deposit or withdrawal, and never rush a transfer.</p>

<h2>4. Your Responsibilities</h2>
<p>You are responsible for selecting the correct asset and network, verifying destination addresses, and understanding the fees shown before you confirm. PaishaPay cannot recover funds sent in error or lost due to on-chain irreversibility.</p>

<h2>5. Important Notices</h2>
<p>This notice does not cover every crypto risk. For general platform risks see the <a href="/pages/risk-disclosure">Risk Disclosure</a>. Fees and confirmation requirements may be updated from time to time and are shown before you confirm. Questions can be sent to <a href="mailto:support@poisapay.com">support@poisapay.com</a>.</p>
HTML,
    ],
    [
        'slug' => 'complaint-policy',
        'title' => 'Complaint Policy',
        'meta_description' => 'How to raise a complaint with PaishaPay, what to include, how we acknowledge and handle it, and how to escalate.',
        'content' => <<<'HTML'
<p>This Complaint Policy explains how to raise a concern with PaishaPay and how we handle complaints. We take feedback seriously and aim to resolve issues fairly and promptly. It should be read with our <a href="/pages/refund-policy">Refund Policy</a> and <a href="/pages/chargeback-policy">Chargeback Policy</a>.</p>

<h2>1. Introduction and Purpose</h2>
<p>If something has gone wrong or you are unhappy with a service, we want to hear about it and put it right where we can. This policy tells you how to complain, what information helps us, and how we respond.</p>

<h2>2. How to Complain</h2>
<p>You can raise a complaint in two ways:</p>
<ul>
<li>Open a <strong>Support ticket</strong> from within the PaishaPay app.</li>
<li>Email us at <a href="mailto:support@poisapay.com">support@poisapay.com</a>.</li>
</ul>

<h2>3. What to Include</h2>
<p>To help us investigate quickly, please include:</p>
<ul>
<li>Your account email and, where relevant, the transaction reference.</li>
<li>A clear description of what happened and when.</li>
<li>The outcome you are seeking.</li>
<li>Any supporting evidence, such as screenshots.</li>
</ul>

<h2>4. Acknowledgement and Resolution</h2>
<p>We aim to acknowledge complaints promptly and to investigate them thoroughly and fairly. We will keep you informed and work to resolve your complaint within a reasonable timeframe, using reasonable efforts given the nature and complexity of the issue. Some matters, such as those involving external partners or regulatory considerations, may take longer.</p>

<h2>5. Escalation</h2>
<p>If you are not satisfied with the initial response, you may ask for your complaint to be escalated for further review. Explain why you remain dissatisfied and provide any additional information, and a further review will be undertaken.</p>

<h2>6. Records</h2>
<p>We keep records of complaints and their outcomes to meet our obligations and to improve our service. These records are handled in line with our <a href="/pages/privacy">Privacy Policy</a>.</p>

<h2>7. Related Matters</h2>
<p>If your complaint concerns a refund, please also review our <a href="/pages/refund-policy">Refund Policy</a>. Note that PaishaPay does not support card chargebacks; refunds are merchant-initiated only, as explained in our <a href="/pages/chargeback-policy">Chargeback Policy</a>.</p>

<h2>8. Important Notices</h2>
<p>Timelines in this policy are targets based on reasonable efforts, not guarantees. Governing law is the law of the jurisdiction in which PaishaPay is established and operates; the operator sets the specific jurisdiction and venue. General support is available at <a href="mailto:support@poisapay.com">support@poisapay.com</a>.</p>
HTML,
    ],
    [
        'slug' => 'electronic-communication-consent',
        'title' => 'Electronic Communication Consent',
        'meta_description' => 'By using PaishaPay you consent to receive agreements, disclosures, notices, and security messages electronically.',
        'content' => <<<'HTML'
<p>This Electronic Communication Consent explains that PaishaPay communicates with you electronically and that, by using the platform, you agree to receive important information this way. It should be read with our <a href="/pages/privacy">Privacy Policy</a> and the notification controls described below.</p>

<h2>1. Introduction and Purpose</h2>
<p>PaishaPay operates primarily online. To provide our services efficiently and securely, we deliver agreements, disclosures, and notices electronically rather than on paper. This consent sets out what that means for you.</p>

<h2>2. Your Consent</h2>
<p>By creating and using a PaishaPay account, you consent to receive the following electronically:</p>
<ul>
<li>Agreements and their updates.</li>
<li>Legal and regulatory disclosures.</li>
<li>Notices, statements, and confirmations relating to your account and transactions.</li>
<li>Security and service messages.</li>
</ul>
<p>We deliver these through channels such as in-app messages, email, and push notifications.</p>

<h2>3. Mandatory Security Notices</h2>
<p>Certain communications are essential to protecting your account and cannot be switched off. Security notifications — such as login alerts and messages about sensitive actions — are always delivered and cannot be opted out of while you hold an account.</p>

<h2>4. Managing Other Notifications</h2>
<p>You control non-security notification categories and may choose which optional messages you receive and through which channels, which can include in-app, email, and push, and where offered SMS, WhatsApp, or Telegram. Managing these preferences does not affect mandatory security notices.</p>

<h2>5. Keeping Your Details Current</h2>
<p>You are responsible for keeping your contact details, especially your email address, accurate and up to date, and for maintaining access to them. If your details are out of date, you may miss important communications, and you remain bound by notices we have validly sent.</p>

<h2>6. Withdrawing Consent</h2>
<p>You may withdraw your consent to certain non-essential electronic communications, but because PaishaPay is delivered electronically, withdrawing consent to essential communications is not possible while you use the platform and may require you to close your account. Withdrawing consent may limit or end your ability to use the service.</p>

<h2>7. Hardware and Software You Need</h2>
<p>To receive and access electronic communications you need a compatible device, a current web browser or the PaishaPay app, a valid email account, and a reliable internet connection. You are responsible for maintaining these.</p>

<h2>8. Important Notices</h2>
<p>This consent may be updated from time to time; the current version governs. For how your data is used and protected, see our <a href="/pages/privacy">Privacy Policy</a>. Questions can be sent to <a href="mailto:support@poisapay.com">support@poisapay.com</a>.</p>
HTML,
    ],
];
