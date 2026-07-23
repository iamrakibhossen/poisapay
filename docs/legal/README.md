**DRAFT — for legal review. Not legal advice.**

Version 0.1-draft
Last updated: [PLACEHOLDER: date]

# PoisaPay Legal & Compliance Documentation

This directory contains the DRAFT legal, data-protection, financial-crime, and risk policy set for PoisaPay, a stablecoin payment platform. These are Wave 8 deliverables of the production-readiness engagement.

---

## Global disclaimer

**Every document in this directory is a DRAFT prepared for internal use and legal review. Nothing here is legal advice, and nothing here creates any binding obligation or public representation until it has been reviewed, completed, and approved by qualified counsel and platform management.**

**Licensing status: TO BE DETERMINED — obtain before launch.** No document in this set claims, and PoisaPay does not hold, any licence, registration, authorisation, or regulatory approval in any jurisdiction. All such items are marked `[PLACEHOLDER]` and must be resolved before any live customer launch. Do not publish, distribute, or rely on these documents while they remain in draft.

---

## Document index

| # | Document | Description |
| --- | --- | --- |
| 1 | [terms-of-service.md](terms-of-service.md) | Master customer contract: eligibility, services, custodial wallet terms, transfers/exchange/withdrawal, fees, prohibited uses, suspension/termination, crypto risk disclosures, liability, indemnity, dispute resolution, governing law. |
| 2 | [privacy-policy.md](privacy-policy.md) | How PoisaPay collects, uses, shares, and protects personal data; legal bases, processors, international transfers, retention, and data-subject rights. |
| 3 | [cookie-policy.md](cookie-policy.md) | Cookies and local storage used by the platform, their categories, consent, and how to manage them. |
| 4 | [refund-policy.md](refund-policy.md) | Finality of on-chain/stablecoin transactions and when refunds, reversals, card disputes, and chargebacks may apply; non-refundable fees. |
| 5 | [card-terms.md](card-terms.md) | Cardholder agreement for virtual and physical cards: JIT authorization, limits, MCC/country blocks, FX, liability, disputes, issuer disclosure. |
| 6 | [kyc-policy.md](kyc-policy.md) | Customer identification and due diligence: the Tier 0/1/2 model, documents per tier, verification methods, EDD/PEP, ongoing due diligence, offboarding. |
| 7 | [aml-cft-policy.md](aml-cft-policy.md) | AML/CFT program: risk-based approach, sanctions/PEP screening, transaction monitoring, red flags, SAR/STR workflow, MLRO role, training, audit, Travel Rule (roadmap). |
| 8 | [kyt-policy.md](kyt-policy.md) | Know-Your-Transaction / blockchain analytics: on-chain risk scoring, deposit/withdrawal screening, address whitelist + cooldown, velocity limits, escalation. |
| 9 | [risk-policy.md](risk-policy.md) | Enterprise risk framework: operational, custody/key-management, market/FX, liquidity/solvency, fraud, compliance, and cyber risk; appetite, controls, KRIs, BCP. |
| 10 | [compliance-documentation.md](compliance-documentation.md) | Governance overview tying the set together: roles, policy register, control matrix mapping policy commitments to technical controls, licensing status, audit cadence. |
| 11 | [README.md](README.md) | This index, global disclaimer, versioning note, and the counsel placeholder checklist. |

Suggested reading order for reviewers: 11 (this file) → 10 (overview & control matrix) → 1–5 (customer-facing) → 6–8 (financial crime) → 9 (risk).

---

## Versioning

- All documents are at **Version 0.1-draft**.
- Each document carries its own version line and a `Last updated: [PLACEHOLDER: date]` line under its DRAFT banner.
- Update the version and date on each material change. Keep the policy register in `compliance-documentation.md` in sync when documents are added, removed, or re-owned.
- Change control and approval are described in `compliance-documentation.md` Section 2 and Section 7.

---

## Counsel checklist — `[PLACEHOLDER]` items to complete before launch

Counsel and platform management must resolve every `[PLACEHOLDER]` marker across the set. The list below groups the recurring items; individual documents may contain additional context-specific markers, so also search each file for `[PLACEHOLDER`.

### A. Entity, jurisdiction & governing law
- [ ] Legal entity name and registered address
- [ ] Governing law and jurisdiction / dispute-resolution venue
- [ ] Arbitral institution, seat/venue (Terms of Service)
- [ ] Base/display currency confirmation (BDT / USD)

### B. Licensing & regulatory status (TO BE DETERMINED — obtain before launch)
- [ ] Money-services / payment / e-money / VASP licence or registration status and issuing authority
- [ ] Card program: issuing bank / program manager / card network / scheme, and scheme rules
- [ ] Local-rail (bank, bKash, Nagad) partner authorisations
- [ ] Deposit-insurance body reference (to confirm PoisaPay is NOT covered)
- [ ] Applicable AML/CFT statutes and regulations
- [ ] Sanctions authorities and local/national designated-persons lists
- [ ] Data-protection authority / registration

### C. Named roles & contacts
- [ ] MLRO / Compliance Officer name and title
- [ ] Chief Risk Officer / Risk Committee name
- [ ] Data Protection Officer (DPO) contact
- [ ] Board / senior-management approver name(s)
- [ ] Support email and in-app support / dispute channel
- [ ] Competent authority / FIU name and filing portal/channel

### D. Vendors & processors
- [ ] Identity / document-verification vendor
- [ ] Liveness / biometric vendor (planned)
- [ ] Sanctions/PEP screening provider
- [ ] Blockchain analytics (KYT) provider
- [ ] Card program manager / issuer
- [ ] Payment service providers for fiat rails
- [ ] International-transfer safeguards (adequacy / SCCs)
- [ ] Travel Rule solution / provider (roadmap)
- [ ] Third-party cookie providers and purposes

### E. Amounts, thresholds & limits
- [ ] KYC tier limits (Tier 1 daily withdrawal, Tier 2 higher limits)
- [ ] Card spending, ATM, and hold-expiry limits
- [ ] Fee amounts (deposit %, withdrawal % + rail fee, FX spread, card fees)
- [ ] AML transaction-monitoring and KYT scoring thresholds
- [ ] Travel Rule threshold (roadmap)
- [ ] Address-whitelist cooldown duration
- [ ] Risk appetite thresholds and tolerances
- [ ] Liability caps / allocation

### F. Time periods & cadences
- [ ] Effective date(s) and amendment notice period
- [ ] Record-retention periods (KYC/AML, audit logs) per statute
- [ ] Dispute / chargeback windows and investigation timeframes
- [ ] RTO / RPO business-continuity targets
- [ ] Policy-review, audit, and training frequencies
- [ ] Age of majority / minimum age

### G. Other confirmations
- [ ] Custody, segregation, and treasury-pooling disclosures as confirmed by legal/compliance
- [ ] Any stablecoin/issuer-specific risk disclosures
- [ ] Mandatory consumer-protection rights for the governing jurisdiction
- [ ] Unclaimed-property / dormancy law applicability
- [ ] Insurance coverage confirmation (do not assert coverage unless confirmed)
- [ ] Enforceability confirmation for arbitration/limitation clauses in the governing jurisdiction

---

*Do not remove the DRAFT banners or the licensing-status warning until counsel has approved each document for its intended use. DRAFT — for legal review. Not legal advice.*
