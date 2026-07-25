#!/usr/bin/env python3
import re, os
P="/Users/rakibhossen/Apps/poisapay/database/schema"
dump=open(P+"/_full_dump.sql").read()

DOMAINS = {
 "core_users": ["users","password_reset_tokens","sessions","personal_access_tokens","notifications","user_devices","user_push_tokens","otp_codes","login_histories"],
 "core_system": ["migrations","cache","cache_locks","jobs","job_batches","failed_jobs"],
 "auth_rbac": ["permissions","roles","model_has_permissions","model_has_roles","role_has_permissions","admins","admin_password_reset_tokens"],
 "kyc_compliance": ["kyc_profiles","screening_results","travel_rule_records","aml_alerts","compliance_cases","compliance_list_entries"],
 "ledger": ["journal_entries","ledger_lines","ledger_accounts","account_balances"],
 "registry_wallet": ["chains","assets","currencies","custody_xpubs","deposit_addresses","onchain_txs","evm_nonces","gas_wallets","gas_sponsorships","rpc_endpoints","address_book_entries","user_favorite_assets","user_spending_priority"],
 "deposits": ["deposits","deposit_methods"],
 "withdrawals": ["withdrawals","withdrawal_methods","payout_accounts","sweeps","broadcast_attempts"],
 "treasury": ["treasury_moves","cold_refill_requests","reconciliation_runs","profit_payouts","revenue_withdrawals"],
 "exchange": ["fx_quotes","conversions","trading_pairs","ramp_orders"],
 "cards": ["cards","card_authorizations","card_disputes","card_providers","provider_accounts","card_provider_logs","card_metadata","card_webhooks"],
 "merchant_payments": ["merchants","merchant_invoices"],
 "p2p": [t for t in re.findall(r"CREATE TABLE public\.(\w+)", dump) if t.startswith("p2p_")],
 "shop": [t for t in re.findall(r"CREATE TABLE public\.(\w+)", dump) if t.startswith("shop_")],
 "rewards": ["reward_grants","reward_campaigns","referrals"],
 "notifications_webhooks": ["notification_preferences","notification_templates","announcements","webhook_endpoints","webhook_deliveries","webhook_logs"],
 "security_audit": ["security_events","audit_logs"],
 "support": ["support_tickets","support_messages"],
 "transfers": ["transfers"],
 "cms": ["pages","faqs"],
 "analytics_settings": ["analytics_daily_metrics","system_settings"],
}
# dependency order (parents before children); FKs applied globally last
ORDER=["core_system","core_users","auth_rbac","registry_wallet","ledger","kyc_compliance",
 "deposits","withdrawals","treasury","exchange","transfers","cards","merchant_payments",
 "p2p","shop","rewards","notifications_webhooks","security_audit","support","cms","analytics_settings"]

alltables=re.findall(r"CREATE TABLE public\.(\w+)", dump)
mapped=set(sum(DOMAINS.values(),[]))
for t in alltables:
    if t not in mapped: DOMAINS.setdefault("misc",[]).append(t)
if "misc" in DOMAINS and "misc" not in ORDER: ORDER.append("misc")
t2d={t:d for d,ts in DOMAINS.items() for t in ts}

# Extract statements
def blocks(pattern):
    return list(re.finditer(pattern, dump))

# CREATE TABLE ... ); (multiline)
tbl={}
for m in re.finditer(r"CREATE TABLE public\.(\w+) \((.*?)\n\);", dump, re.S):
    tbl_name=m.group(1); tbl[tbl_name]=m.group(0)+"\n"
# CREATE SEQUENCE + ALTER SEQUENCE OWNED + ALTER TABLE ALTER COLUMN default (identity) — keep with owning table
seqs={}
for m in re.finditer(r"CREATE SEQUENCE public\.(\w+)_seq.*?;", dump, re.S):
    seqs.setdefault(m.group(1), []).append(m.group(0))
# Indexes
idx={}
for m in re.finditer(r"CREATE (?:UNIQUE )?INDEX \w+ ON public\.(\w+) [^;]*;", dump):
    idx.setdefault(m.group(1),[]).append(m.group(0))
# Constraints: PK/UNIQUE (with table) vs FK (global)
pk={}; fks=[]
for m in re.finditer(r"ALTER TABLE ONLY public\.(\w+)\s+ADD CONSTRAINT [^;]*?;", dump, re.S):
    stmt=m.group(0); tname=m.group(1)
    if "FOREIGN KEY" in stmt: fks.append(stmt)
    else: pk.setdefault(tname,[]).append(stmt)

os.makedirs(P,exist_ok=True)
header=lambda d:f"-- ============================================================\n-- Module: {d}\n-- GENERATED from Laravel migrations (source of truth) via pg_dump.\n-- Do not edit by hand; regenerate with database/schema/regenerate.sh\n-- ============================================================\n\n"
written=[]
for d in ORDER:
    ts=[t for t in DOMAINS.get(d,[]) if t in tbl]
    if not ts: continue
    out=header(d)
    for t in ts:
        for s in seqs.get(t,[]): out+=s+"\n\n"
        out+=tbl[t]+"\n"
        for s in pk.get(t,[]): out+=s+"\n"
        for s in idx.get(t,[]): out+=s+"\n"
        out+="\n"
    fn=f"{ORDER.index(d)+1:02d}_{d}.sql"
    open(P+"/"+fn,"w").write(out)
    written.append(fn)

open(P+"/99_foreign_keys.sql","w").write(header("foreign_keys (applied last — all tables exist)")+"\n".join(fks)+"\n")
written.append("99_foreign_keys.sql")

main="-- main.sql — loads the modular schema in dependency order.\n"
main+="-- GENERATED snapshot for documentation/reference. The AUTHORITATIVE\n-- schema is the Laravel migrations in database/migrations/.\n\n"
for fn in written: main+=f"\\i {fn}\n"
open(P+"/main.sql","w").write(main)
os.remove(P+"/_full_dump.sql")
print("wrote", len(written), "module files + main.sql")
print("tables:", len(tbl), "fks:", len(fks))
for fn in written: print("  ",fn)
