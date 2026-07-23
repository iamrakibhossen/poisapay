# PoisaPay — Compliance Depth (Wave 5)

Builds on the Wave-3 adapter seams (`ScreeningProvider`, `KycProvider`). Everything
here is buildable without vendor accounts; the real screening/KYC-liveness vendors
just bind to the existing seams. Feature-flagged, backward compatible, tested.

## Delivered

| # | Item | Where |
|---|---|---|
| 5.4 | Persistent blacklist/whitelist | `compliance_list_entries` + `ComplianceListEntry` + `ComplianceListService` |
| 5.4 | Country-risk screening | `ComplianceListService::countryRisk()` (config `high_risk_countries` + denylisted countries); wired into `StubScreeningProvider` |
| 5.4 | Admin management | `/admin/compliance-lists` (add/remove denylist/watchlist/whitelist entries) |
| 5.6 | Structured SAR | `compliance_cases.sar_activity_type/sar_narrative/sar_amount/sar_filed_at`; `ComplianceCaseService::fileSar()` (backward-compatible new args) |
| 5.6 | Compliance export | `/admin/compliance/export/{cases,alerts}` streamed CSV (incl. SAR fields) |
| 5.7 | Freeze at all touchpoints | `AccountGuard::assertActive()` added to transfers, exchange/swaps, merchant payments (withdrawal/off-ramp/card already had it) |
| 5.5 | Travel Rule schema | `travel_rule_records` + `TravelRuleRecord` |
| 5.5 | Travel Rule adapter | `TravelRuleProvider` + `StubTravelRuleProvider` via `config/providers.php` |
| 5.5 | Travel Rule capture | `TravelRuleService` (threshold + originator/beneficiary), wired into on-chain withdrawals ≥ threshold |

## Screening now consults (in order)
1. Persistent denylist (name) → **Hit**; legacy settings denylist still honoured.
2. Persistent watchlist (name) → **Review**; legacy settings watchlist still honoured.
3. Country risk (subject's latest KYC country in high-risk list) → escalate Clear → **Review**.

`ScreeningService`'s public API is unchanged; `TransactionMonitor` and onboarding
listeners are unaffected. Real vendor = bind a different `ScreeningProvider`.

## Freeze enforcement (5.7)
A frozen account is now rejected at **every** value-movement path:
withdrawal · off-ramp · card authorization · **internal transfer** · **exchange/swap** · **merchant payment**.
Centralised in `AccountGuard::assertActive()` so no path can be missed.

## Travel Rule (5.5)
- **Flag:** `security_travel_rule` (default **off** — enabling means the deployment is ready to collect/transmit beneficiary data).
- **Threshold:** `security_travel_rule_threshold` (display units, FATF R.16 ~ USD 1,000).
- On an on-chain withdrawal at/above the threshold, a `travel_rule_record` is captured
  (originator = user, beneficiary = destination address) and handed to the provider.
  The stub records + returns a reference; a real provider (Notabene/TRISA/Sygna) transmits.

## Still needs vendor accounts (bind to existing seams)
- Real sanctions/PEP screening → `ScreeningProvider` (ComplyAdvantage/Refinitiv).
- KYC document + liveness → `KycProvider` (Onfido/SumSub/Jumio).
- Travel Rule network → `TravelRuleProvider` (Notabene/TRISA).
- OFAC/UN/EU list auto-sync into `compliance_list_entries` (a scheduled importer).

## Tests
`tests/Feature/ComplianceDepthTest.php` — 11 tests: freeze at touchpoints + guard,
persistent denylist hit, country-risk review, list membership/whitelist, structured SAR,
CSV export, sanctions-list admin page, Travel Rule provider + capture (above/below threshold).
