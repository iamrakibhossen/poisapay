**DRAFT — for legal review. Not legal advice.**

Version 0.1-draft
Last updated: [PLACEHOLDER: date]

# PoisaPay Customer Identification and Due Diligence (KYC/AML) Policy

This Policy sets out how [PLACEHOLDER: entity name] ("PoisaPay", "we", "us", "our") identifies and verifies its customers and conducts customer due diligence in connection with the PoisaPay stablecoin payment platform. It is intended to support compliance with applicable anti-money-laundering ("AML"), counter-terrorist-financing ("CTF"), and sanctions obligations under [PLACEHOLDER: regulator / licence / applicable law].

---

## 1. Purpose and Scope

1.1 **Purpose.** The purpose of this Policy is to establish a risk-based framework for customer identification, verification, and ongoing due diligence, in order to prevent PoisaPay from being used for money laundering, terrorist financing, sanctions evasion, fraud, or other financial crime.

1.2 **Scope.** This Policy applies to all customers of PoisaPay who open or hold an account, hold custodial stablecoin or cryptocurrency wallets (including USDT on TRON (TRC20) and Ethereum/BSC (ERC20)), transact on the platform, or use PoisaPay Cards.

1.3 **Risk-based approach.** PoisaPay applies a risk-based approach: the extent of identification, verification, and monitoring is proportionate to the money-laundering and terrorist-financing risk presented by the customer, product, transaction, and geography.

---

## 2. Customer Identification Program (CIP)

2.1 PoisaPay operates a Customer Identification Program to establish and verify the identity of each customer before, or shortly after, granting access to regulated functionality, in line with the tiered model in Section 5.

2.2 The CIP collects and records identifying information, which may include name, date of birth, residential address, nationality, government-issued identification details, email address, and phone number, as required for the relevant tier.

2.3 Identity is verified using the methods set out in Section 6, and information is retained as set out in Section 10.

---

## 3. Customer Due Diligence (CDD)

3.1 PoisaPay conducts Customer Due Diligence to understand the customer and the intended nature of the business relationship. CDD includes:

  (a) identifying the customer and verifying identity under the CIP;

  (b) understanding the purpose and intended nature of the relationship;

  (c) screening the customer against sanctions and politically-exposed-person ("PEP") lists;

  (d) assigning a risk score to the customer; and

  (e) conducting ongoing monitoring under Section 7.

3.2 CDD is performed at onboarding and refreshed on a risk-sensitive basis and when trigger events occur (for example, a material change in customer behaviour, a limit-tier upgrade, or an alert from transaction monitoring).

---

## 4. Enhanced Due Diligence (EDD)

4.1 PoisaPay applies Enhanced Due Diligence to customers and situations presenting higher risk, including:

  (a) PEPs, their family members, and known close associates;

  (b) customers connected to higher-risk jurisdictions or sanctions-adjacent geographies;

  (c) high-value activity or unusual transaction patterns; and

  (d) any customer flagged as high risk by risk scoring or transaction monitoring.

4.2 EDD measures may include obtaining additional identification and source-of-funds or source-of-wealth evidence, obtaining senior management approval to establish or continue the relationship, and applying enhanced ongoing monitoring.

---

## 5. Tiered Verification Model

5.1 PoisaPay operates a tiered verification model. A customer's tier determines the functionality available, including withdrawal limits and Card eligibility.

5.2 **Tier summary and limits.**

| Tier | Name | Verification | Withdrawals | Cards | Indicative limits |
|------|------|--------------|-------------|-------|-------------------|
| Tier 0 | Unverified | Email and phone only | Not permitted | Not eligible | [PLACEHOLDER: limit amounts] |
| Tier 1 | Basic | Government-issued ID + selfie | Limited daily withdrawals | Not eligible | [PLACEHOLDER: daily withdrawal limit] |
| Tier 2 | Full | Full verification (ID, proof of address, liveness, source-of-funds where required) | Higher limits | Eligible | [PLACEHOLDER: limit amounts] |

5.3 **Tier 0 — Unverified.** The customer has registered with an email address and phone number only. Tier 0 customers may not make withdrawals and may not obtain a Card. Functionality is limited pending verification.

5.4 **Tier 1 — Basic.** The customer has completed basic identity verification (government-issued ID and a selfie). Tier 1 unlocks limited daily withdrawals [PLACEHOLDER: daily withdrawal limit] but does not make the customer eligible for a Card.

5.5 **Tier 2 — Full.** The customer has completed full verification (see Section 5.6). Tier 2 unlocks higher withdrawal limits [PLACEHOLDER: limit amounts] and makes the customer **eligible for PoisaPay Cards** (subject to the Cardholder Agreement).

5.6 **Documents required per tier.**

  (a) **Tier 0:** verified email address and phone number.

  (b) **Tier 1:** a valid government-issued identity document (for example, passport, national ID, or driving licence) and a selfie for identity matching.

  (c) **Tier 2:** the Tier 1 requirements, plus proof of address, a liveness check, and source-of-funds evidence where required by risk assessment or applicable law.

5.7 Amounts, thresholds, and the display/base currency ([PLACEHOLDER: BDT/USD]) applicable to each tier are set out in the current limits schedule and may be adjusted for regulatory or risk reasons.

---

## 6. Verification Methods

6.1 **Document verification.** Government-issued identity documents are checked for authenticity, validity, and consistency with the information provided by the customer.

6.2 **Liveness / biometric check.** A liveness and biometric-matching check is performed to confirm that the person completing verification is the genuine holder of the identity document. Liveness verification is delivered via a third-party vendor [PLACEHOLDER: liveness / biometric vendor] and is currently *planned* pending integration.

6.3 **Sanctions and PEP screening.** Customers (and, where relevant, connected parties) are screened against sanctions, watchlist, and PEP data. Screening is performed through an adapter-based integration that allows the underlying screening provider [PLACEHOLDER: screening provider] to be configured or changed. Screening is performed at onboarding and on an ongoing basis.

6.4 **Risk scoring.** Each customer is assigned a risk score based on factors including identity attributes, geography, product usage, and transaction behaviour. Risk scores inform the level of due diligence and monitoring applied.

---

## 7. Ongoing Due Diligence and Transaction Monitoring

7.1 **Ongoing monitoring.** PoisaPay conducts ongoing due diligence throughout the customer relationship to ensure that transactions are consistent with its knowledge of the customer and the customer's risk profile.

7.2 **Transaction monitoring (KYT).** PoisaPay monitors transactions and, where applicable, on-chain activity (know-your-transaction) to detect unusual or suspicious patterns, including activity inconsistent with a customer's profile, structuring, and exposure to high-risk counterparties or addresses.

7.3 **Periodic re-verification.** Customer information and verification are refreshed periodically on a risk-sensitive basis and upon trigger events, including tier upgrades and monitoring alerts.

7.4 **Alerts and investigation.** Alerts generated by monitoring or screening are reviewed and investigated. Where warranted, PoisaPay escalates matters internally.

---

## 8. Suspicious Activity and SAR Workflow

8.1 Where PoisaPay identifies activity that it knows, suspects, or has reasonable grounds to suspect involves the proceeds of crime, money laundering, terrorist financing, or sanctions evasion, it follows an internal escalation and Suspicious Activity Report ("SAR") workflow.

8.2 Escalations are reviewed by the [PLACEHOLDER: MLRO / Compliance role], who determines whether a report to the relevant authority [PLACEHOLDER: regulator / FIU] is required and whether any account restrictions should be applied.

8.3 PoisaPay maintains "tipping-off" controls and does not disclose to a customer that a SAR has been or may be filed, except as permitted by law.

---

## 9. Sanctions, Blacklist/Whitelist, and Account Freeze

9.1 PoisaPay screens against sanctions lists and applies controls to prevent prohibited persons and jurisdictions from accessing the platform.

9.2 PoisaPay maintains blacklist and whitelist controls at the customer, counterparty, and address level to block or permit activity in line with its risk assessment and legal obligations.

9.3 PoisaPay may freeze an account or specific functionality (including withdrawals and Cards) where required for compliance, sanctions, legal, or risk reasons, including pending investigation of a monitoring or screening alert.

---

## 10. Record-Keeping

10.1 PoisaPay retains customer identification and verification records, due-diligence documentation, transaction records, screening results, and records of investigations and reports for the period required by applicable law [PLACEHOLDER: retention period].

10.2 Records are stored securely, with access restricted to authorized personnel, and are made available to regulators and authorities as required by law.

---

## 11. Refusal, Offboarding, and Account Freeze

11.1 PoisaPay may refuse to onboard, or may offboard, a customer where identity cannot be verified, where required information is not provided, where screening indicates a prohibited or unacceptable risk, or where continuing the relationship would breach law or this Policy.

11.2 Refusal or offboarding may include freezing the account, restricting withdrawals, and cancelling Cards, subject to any legal obligations to preserve funds or records.

11.3 Decisions to refuse, offboard, or freeze are documented and, where appropriate, escalated to the [PLACEHOLDER: MLRO / Compliance role].

---

## 12. PEP Handling and Senior Sign-Off

12.1 Where a customer is identified as a PEP (or a family member or close associate of a PEP), PoisaPay applies EDD under Section 4 and obtains senior management or [PLACEHOLDER: MLRO / Compliance role] approval before establishing or continuing the relationship.

12.2 PEP relationships are subject to enhanced ongoing monitoring and periodic senior review.

---

## 13. Roles and Responsibilities

13.1 **MLRO / Compliance.** The [PLACEHOLDER: MLRO / Compliance Officer name] holds overall responsibility for this Policy, for SAR determinations, and for the AML/CTF program.

13.2 **Operations and support teams** are responsible for executing onboarding, verification, and monitoring processes in line with this Policy and for escalating alerts appropriately.

13.3 **Senior management** is responsible for supporting a culture of compliance and for approving high-risk relationships where required.

13.4 All staff are responsible for adhering to this Policy and completing relevant AML/CTF training.

---

## 14. Review of this Policy

14.1 This Policy is reviewed periodically and updated in response to changes in law, regulation, product, and risk [PLACEHOLDER: review frequency / effective date].

---

*This is a draft document prepared for internal and legal review. It does not constitute legal advice, and no reliance should be placed on it until it has been reviewed, completed, and approved by qualified legal counsel.*
