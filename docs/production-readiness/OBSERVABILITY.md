# PoisaPay — Scale, Observability & DR (Wave 7)

## Delivered

| Item | Where |
|---|---|
| Nightly DB backup + retention | `poisapay:backup` (pg_dump+gzip, `--keep` days) + `BackupService` prune; scheduled 02:30 |
| Data retention | `poisapay:retention` (`--days`, default 90) prunes login history + acknowledged security events; scheduled weekly |
| Audit-chain heartbeat | `poisapay:audit-verify` scheduled 03:00 (exit 1 on tamper) |
| Insolvency alerting | `ReconciliationService` → durable `security_events` (type `insolvency`, critical) + `notifyAdmins` + `Log::critical` |
| Health checks | Laravel `/up` (wired to Docker/K8s probes) + admin blockchain-health page |
| OpenAPI + docs | `resources/openapi.yaml` served at `GET /api/openapi.json`; Swagger UI at `GET /api/docs` |
| Load-test harness | `tests/load/api-load.js` (k6) |
| Error tracking (opt-in) | Sentry via env (`SENTRY_LARAVEL_DSN`) |

## Backups & Disaster Recovery

**Backup (automated, nightly):**
```bash
php artisan poisapay:backup --keep=14      # → storage/app/backups/poisapay-YYYYMMDD-HHMMSS.sql.gz
```
Ship dumps off-box in production (S3 lifecycle / rsync to a second region). The command
prunes local dumps older than `--keep` days.

**Restore drill (run monthly against a scratch DB):**
```bash
createdb poisapay_restore
gunzip -c storage/app/backups/poisapay-<ts>.sql.gz | psql -d poisapay_restore
php artisan migrate:status --database=... # sanity check schema head
php artisan poisapay:audit-verify          # prove the audit chain survived
```

**DR targets (set per environment):** RPO ≤ 24h (nightly) — tighten with WAL archiving /
PITR for RPO ≈ minutes; RTO ≤ 1h. Document the actual figures in the runbook once the
managed-Postgres provider (PITR window) is chosen.

## Alerting pipeline
- `LOG_STACK=daily,slack` + `LOG_LEVEL=warning` routes warnings+ to Slack in production.
- Insolvency (`Log::critical` + operator notification + `security_events`) is the top page.
- Add Sentry for exceptions/traces: `composer require sentry/sentry-laravel` then set
  `SENTRY_LARAVEL_DSN`; Laravel auto-discovers it (no-op without a DSN).

## Load testing
```bash
k6 run -e BASE_URL=https://staging.example -e TOKEN=<staging-bearer> tests/load/api-load.js
```
Thresholds: p95 < 800ms, error rate < 1%. Focus areas to watch under load: the ledger
balanced-entry trigger and `FOR UPDATE` balance locks during concurrent withdrawals.

## Table partitioning (runbook — declarative, not an in-place migration)
High-volume tables (`ledger_lines`, `onchain_txs`, `audit_logs`) should be RANGE-partitioned
by month once volume warrants it. This is intentionally NOT an automated migration (rewriting
live financial tables is high-risk); do it as a planned maintenance step:
```sql
-- Example: partition onchain_txs by created_at month (new table + backfill + swap).
CREATE TABLE onchain_txs_p (LIKE onchain_txs INCLUDING ALL) PARTITION BY RANGE (created_at);
-- create monthly partitions, backfill, then swap under a brief lock.
```
Pair with a monthly `CREATE PARTITION` cron and drop/detach beyond the retention window.

## Still open (needs infra decisions/accounts)
- Managed Postgres with PITR (RPO minutes) + read replica.
- APM dashboards (Sentry/Datadog) + PagerDuty escalation policy.
- Blue-green / rolling deploy + autoscaling (K8s manifests / ECS service).
