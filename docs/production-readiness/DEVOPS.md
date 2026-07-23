# PoisaPay — DevOps & Local/Prod Runbook (Wave 0)

This is the safety net delivered in **Wave 0** of the roadmap: reproducible builds, CI, static analysis, and process supervision — added *before* any money-handling code changes so regressions are caught automatically.

## What Wave 0 delivered

| Artifact | Purpose |
|---|---|
| `Dockerfile` | Multi-stage production image (Vite assets → composer no-dev → PHP-FPM 8.3 runtime) |
| `docker-compose.yml` | Full stack: nginx, php-fpm, horizon, scheduler, reverb, postgres, redis |
| `docker/nginx/default.conf` | Web front door + `/up` health probe |
| `docker/php/php.ini` | Production PHP/OPcache/JIT tuning |
| `.github/workflows/ci.yml` | CI: Pint → PHPStan → Pest (coverage) → asset build, on Postgres+Redis services |
| `phpstan.neon` + baseline | Larastan level 6 static analysis; baseline pins legacy debt so only *new* issues fail CI |
| `pint.json` | Code style (Laravel preset) |
| `deploy/supervisor/*.conf` | Process supervision for non-Docker (VM/bare-metal) deploys |
| `.env.production.example` | Hardened production env template (debug off, session encryption, Redis, S3, KMS reminder) |
| composer scripts | `composer lint`, `composer analyse`, `composer ci` |

## Run the full stack locally (Docker)

```bash
cp .env.production.example .env          # then set APP_URL, DB_*, REDIS_* for local
docker compose build
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --force
docker compose up -d
# app on http://localhost:8080  (health: http://localhost:8080/up)
```

> The base compose is **production-shaped** (code baked into the image, shared with nginx via the `app_code` volume). After changing code, rebuild and recreate the volume:
> `docker compose build && docker compose up -d --force-recreate --renew-anon-volumes`.
> For live-reload dev, add a `docker-compose.override.yml` that bind-mounts `.:/var/www/html`.

## Quality gates (run before pushing)

```bash
composer ci        # pint --test + phpstan + tests   (mirrors GitHub Actions)
composer lint      # auto-fix code style
composer analyse   # static analysis only
```

## Regenerating the PHPStan baseline

The baseline captures pre-existing debt so CI blocks *new* violations without failing on legacy code. After a large refactor that clears debt:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

## Non-Docker deploy (VM / bare-metal)

1. Provision PHP 8.3 (`pdo_pgsql bcmath gmp intl pcntl zip opcache`), PostgreSQL 16, Redis 7, nginx.
2. `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
3. `php artisan config:cache route:cache view:cache event:cache`
4. Copy `deploy/supervisor/*.conf` to `/etc/supervisor/conf.d/`, adjust paths, `supervisorctl reread && update`.
5. Point nginx at `public/` using `docker/nginx/default.conf` as the template (swap `fastcgi_pass` to the local socket).

## Still open (later waves — tracked in CHECKLIST.md)
- Backups + PITR + restore drill (Wave 7 / OPS-*)
- APM + Sentry + PagerDuty/Slack alerting (Wave 7 / OBS-*)
- Blue-green / rolling deploy + auto-scaling (Wave 7)
- Table partitioning + retention (Wave 7 / DB-*)
- Load & penetration testing (Wave 7 / QA-*)
