**DRAFT — for legal review. Not legal advice.**

Version 0.1-draft
Last updated: [PLACEHOLDER: date]

# PoisaPay Cardholder Agreement

This Cardholder Agreement (the "Agreement") governs the issuance and use of virtual and physical payment cards ("Cards") made available through the PoisaPay platform operated by [PLACEHOLDER: entity name] ("PoisaPay", "we", "us", "our"). By requesting, activating, or using a Card, you ("you", "Cardholder") agree to be bound by this Agreement. If you do not agree, do not request or use a Card.

Cards are issued by [PLACEHOLDER: issuing bank / issuer name] pursuant to a licence from [PLACEHOLDER: card network / scheme name] and are managed in partnership with [PLACEHOLDER: card program manager name] (together, the "Card Program Partners"). The Card Program is subject to the applicable rules of [PLACEHOLDER: card scheme rules] (the "Scheme Rules") and to the terms of the Card Program Partners, which may apply in addition to this Agreement.

---

## 1. Definitions

1.1 "Available Balance" means the value of eligible stablecoin and cryptocurrency assets held in your custodial PoisaPay wallet that are available to fund Card spending at the time of authorization.

1.2 "Authorization" means a real-time request from a merchant or acquirer to reserve or debit funds for a transaction.

1.3 "Funding Balance" means the specific stablecoin or cryptocurrency balance (e.g. USDT on TRON (TRC20) or Ethereum/BSC (ERC20)) designated to fund your Card.

1.4 "JIT Authorization" (just-in-time authorization) means the process by which a Card Authorization triggers a real-time evaluation and debit of your Funding Balance, as described in Section 4.

1.5 "MCC" means a Merchant Category Code assigned to a merchant by the card scheme.

1.6 "Settlement" means the final clearing of a transaction after it has been authorized.

1.7 "Display Currency" means the base or display currency of your account, being [PLACEHOLDER: BDT/USD].

---

## 2. Eligibility

2.1 Cards are available only to users who have completed identity verification to **KYC Tier 2 (Full Verification)** in accordance with the PoisaPay KYC Policy. You must maintain Tier 2 status for the duration of your use of a Card.

2.2 You must be at least the age of majority in your jurisdiction and legally capable of entering into this Agreement.

2.3 We may decline to issue a Card, or may suspend or close a Card, where you do not meet or cease to meet eligibility requirements, including where sanctions, PEP, or transaction-monitoring screening indicates elevated risk.

2.4 Cards may not be available in all countries. Eligibility may be restricted based on your country of residence and applicable law, the Scheme Rules, and the requirements of the Card Program Partners.

---

## 3. Types of Card

3.1 **Virtual Cards.** A virtual Card is issued electronically for use in online, in-app, and mobile-wallet transactions. Virtual Cards have no physical form and are available immediately upon issuance, subject to verification.

3.2 **Physical Cards.** A physical Card is a plastic card mailed to your registered address for in-person, contactless, and ATM use, in addition to online use. Physical Cards must be activated before use and may be subject to shipping timelines and delivery restrictions.

3.3 You may hold one or more Cards subject to limits we set. Each Card may be individually configured with its own limits and controls, subject to Sections 6 and 7.

---

## 4. Funding and JIT Authorization

4.1 **Crypto-funded spending.** Your Card is funded from your custodial PoisaPay Funding Balance denominated in stablecoin or cryptocurrency. PoisaPay does not maintain a separate fiat float on your behalf; each transaction is funded from your Funding Balance at the time of Authorization.

4.2 **How JIT Authorization works.** When you use your Card:

  (a) the merchant or acquirer submits an Authorization request to the Card scheme;

  (b) PoisaPay receives the Authorization request in real time and performs a JIT Authorization check, evaluating the transaction against your Available Balance, your spending and ATM limits, and applicable MCC, country, and merchant restrictions;

  (c) if the transaction is in a currency different from your Funding Balance, PoisaPay converts the transaction amount using the time-of-authorization FX rate described in Section 8;

  (d) if the check passes, PoisaPay places a hold on, and/or debits, the corresponding amount of your Funding Balance and approves the Authorization; and

  (e) if the check fails (for example, insufficient Available Balance, a limit is exceeded, or a restriction applies), the Authorization is declined.

4.3 **Real-time debit.** By using your Card, you authorize PoisaPay to debit your Funding Balance in the amount of each approved transaction (including applicable fees and FX conversion) at the time of Authorization. The amount of cryptocurrency or stablecoin debited is determined at that time and is not adjusted for subsequent movements in market or FX rates, except as described in Sections 5 and 8.

4.4 **Sufficient balance.** You are responsible for maintaining a sufficient Available Balance to cover your intended transactions, holds, and fees. Transactions that would exceed your Available Balance will be declined.

---

## 5. Authorizations, Holds, and Settlement

5.1 **Pending vs. settled.** An approved Authorization creates a "pending" transaction and a corresponding hold on your Funding Balance. A transaction becomes "settled" when the merchant submits it for clearing, which may occur some time after Authorization.

5.2 **Holds.** A hold reduces your Available Balance by the authorized amount (which may include an estimated amount for certain merchant types, such as hotels, car rental, or fuel). Held funds are not available for other transactions while the hold is in place.

5.3 **Hold release.** If a pending Authorization is not settled within the window permitted by the Scheme Rules [PLACEHOLDER: hold-expiry period], or if the merchant reverses or cancels the Authorization, the hold is released and the corresponding funds are returned to your Available Balance. The final settled amount may differ from the authorized amount; where it does, we will adjust the debit to your Funding Balance accordingly.

5.4 **Refunds and reversals.** Refunds, reversals, and credits are returned to your Funding Balance and may be converted back to your Funding Balance currency at the FX rate applicable at the time the credit is processed, which may differ from the rate applied to the original transaction.

---

## 6. Spending Limits and ATM Limits

6.1 Each Card is subject to spending limits, which may include per-transaction, daily, weekly, and monthly limits [PLACEHOLDER: limit amounts].

6.2 Physical Cards enabling ATM withdrawals are subject to separate ATM limits, which may include per-withdrawal, daily, and monthly ATM limits [PLACEHOLDER: ATM limit amounts].

6.3 Limits may be set by PoisaPay, by the Card Program Partners, or configured by you within permitted ranges. We may change limits for risk, regulatory, or scheme-compliance reasons at any time.

6.4 Transactions that would cause a limit to be exceeded will be declined at JIT Authorization.

---

## 7. Merchant, MCC, and Country Restrictions

7.1 **MCC blocks.** You may enable or disable spending by Merchant Category Code, and PoisaPay may block certain MCCs (for example, categories associated with elevated risk or prohibited activity) for regulatory or risk reasons.

7.2 **Country blocks.** You may enable or disable spending by merchant country, and PoisaPay may block transactions originating in certain countries, including in connection with sanctions and geographic risk controls.

7.3 **Merchant blocks.** PoisaPay may block or decline transactions from specific merchants or acquirers.

7.4 **Prohibited use.** You must not use your Card for any unlawful purpose, for transactions prohibited by the Scheme Rules, or for categories PoisaPay prohibits. We may decline any transaction that triggers our risk or compliance controls.

---

## 8. FX Conversion and Spread

8.1 **When FX applies.** If the currency of a transaction differs from the currency of your Funding Balance, PoisaPay converts the transaction amount into your Funding Balance currency (and, where relevant, into your Display Currency for reporting).

8.2 **Rate and spread.** Conversion is performed at the FX rate applied by PoisaPay at the time of Authorization, which includes an FX spread [PLACEHOLDER: FX spread amount / percentage]. The FX spread is retained by PoisaPay and/or the Card Program Partners as revenue and is in addition to any FX or cross-border fees charged by the Card scheme or issuer.

8.3 **Rate variability.** Cryptocurrency, stablecoin, and fiat FX rates fluctuate. The rate applied to your transaction may differ from rates quoted elsewhere and from the rate applicable to a later refund, reversal, or settlement adjustment.

---

## 9. Fees

9.1 The following fees may apply to your Card. Current amounts are set out in the PoisaPay fee schedule [PLACEHOLDER: fee amounts] and may be updated from time to time:

  (a) **Issuance fee** — charged when a Card is issued [PLACEHOLDER: amount];

  (b) **Monthly fee** — a recurring account or Card maintenance fee [PLACEHOLDER: amount];

  (c) **ATM fee** — charged per ATM withdrawal or balance enquiry [PLACEHOLDER: amount];

  (d) **FX fee / spread** — applied to currency conversion as described in Section 8 [PLACEHOLDER: amount];

  (e) **Replacement fee** — charged to replace a lost, stolen, or damaged Card [PLACEHOLDER: amount]; and

  (f) any other fees disclosed in the fee schedule.

9.2 Fees are debited from your Funding Balance and, where applicable, converted at the prevailing FX rate. We will make reasonable efforts to notify you of material changes to fees in advance of their effective date.

---

## 10. PIN and Card Security

10.1 You are responsible for keeping your Card details, PIN, one-time passcodes, and account credentials confidential. Do not share them with any third party.

10.2 You must take reasonable steps to protect your Card and prevent unauthorized use, including securing the device on which any virtual Card is provisioned.

10.3 PoisaPay may require additional authentication (for example, one-time passcodes or app-based confirmation) for certain transactions in accordance with the Scheme Rules and applicable regulation.

---

## 11. Managing Your Card: Freeze, Unfreeze, Replace, Close

11.1 **Freeze / Unfreeze.** You may freeze a Card to temporarily block new Authorizations, and unfreeze it to resume use. PoisaPay may also freeze a Card for security, risk, compliance, or legal reasons.

11.2 **Replace.** You may request a replacement Card if your Card is lost, stolen, damaged, or compromised. A replacement fee may apply (Section 9). Replacing a Card may change the Card number and require re-provisioning to any digital wallets.

11.3 **Close.** You may close a Card at any time. Closing a Card cancels it permanently; pending Authorizations and holds may need to settle or expire before the closure is complete. Fees already incurred remain payable.

---

## 12. Liability for Unauthorized Transactions; Lost or Stolen Cards

12.1 **Reporting.** You must notify PoisaPay immediately at [PLACEHOLDER: support email] if you believe your Card has been lost or stolen, or that an unauthorized or erroneous transaction has occurred, and in any event within the timeframe required by the Scheme Rules [PLACEHOLDER: reporting window].

12.2 **Prompt action on report.** Upon a timely report, PoisaPay will freeze or cancel the affected Card and, where appropriate, issue a replacement.

12.3 **Liability allocation.** Your liability for unauthorized transactions will be determined in accordance with applicable law, the Scheme Rules, and the terms of the Card Program Partners. You may be liable for losses resulting from your fraud, gross negligence, or failure to safeguard your credentials, or from a failure to report promptly [PLACEHOLDER: liability cap / allocation].

---

## 13. Disputes and Chargebacks

13.1 If you believe a settled transaction is incorrect or unauthorized, you may raise a dispute by contacting [PLACEHOLDER: dispute contact].

13.2 Disputes and chargebacks are handled in accordance with the Scheme Rules and the processes of the Card Program Partners. Scheme-defined timelines apply, including the time within which a dispute must be raised and the time for resolution [PLACEHOLDER: scheme dispute/chargeback timelines].

13.3 You must cooperate with, and provide information reasonably requested for, any dispute or chargeback investigation. Where a dispute is resolved in your favour, the corresponding amount will be credited to your Funding Balance, subject to FX conversion as described in Section 8.

13.4 Merchant disputes concerning goods or services are between you and the merchant; the availability of a chargeback remedy is governed by the Scheme Rules.

---

## 14. Card Program Partners Disclosure

14.1 Cards are issued by [PLACEHOLDER: issuing bank / issuer name] under licence from [PLACEHOLDER: card network / scheme name], and the Card Program is managed by [PLACEHOLDER: card program manager name]. Your use of a Card is also subject to the terms and privacy notices of the Card Program Partners.

14.2 PoisaPay acts as a program participant and technology provider and is not the issuer of the Card. Certain regulatory responsibilities for the Card Program are held by the Card Program Partners under [PLACEHOLDER: regulator / licence].

14.3 In the event of any conflict between this Agreement and the mandatory terms of the Card Program Partners or the Scheme Rules, the latter prevail to the extent of the conflict.

---

## 15. Suspension and Termination

15.1 PoisaPay or the Card Program Partners may suspend, freeze, or terminate your Card or the Card Program, in whole or in part, for reasons including suspected fraud, security concerns, breach of this Agreement, regulatory or scheme requirements, sanctions or compliance screening outcomes, loss of KYC Tier 2 status, or account freeze under the KYC Policy.

15.2 On termination, all Cards are cancelled and outstanding fees remain payable. Pending Authorizations and holds may need to settle or expire.

15.3 Termination of your Card does not by itself terminate your PoisaPay account, and vice versa, except where required.

---

## 16. Changes to this Agreement

16.1 We may amend this Agreement from time to time. We will provide notice of material changes by a reasonable method and, where required, in advance of the effective date [PLACEHOLDER: effective date / notice period]. Continued use of a Card after changes take effect constitutes acceptance.

---

## 17. Governing Law and Disputes

17.1 This Agreement is governed by the laws of [PLACEHOLDER: governing law], without regard to conflict-of-laws principles.

17.2 Any dispute arising out of or relating to this Agreement shall be subject to [PLACEHOLDER: jurisdiction / dispute-resolution mechanism].

---

## 18. Contact

For questions, lost/stolen reporting, or disputes, contact:

- Support: [PLACEHOLDER: support email]
- Disputes: [PLACEHOLDER: dispute contact]

---

*This is a draft document prepared for internal and legal review. It does not constitute legal advice, and no reliance should be placed on it until it has been reviewed, completed, and approved by qualified legal counsel.*
