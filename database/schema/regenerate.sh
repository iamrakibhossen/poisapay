#!/usr/bin/env bash
# Regenerate the modular schema snapshot from a migrated database.
# Laravel migrations (database/migrations) remain the source of truth; this
# directory is a generated, human-readable reference.
set -euo pipefail
DB="${1:-poisapay}"
here="$(cd "$(dirname "$0")" && pwd)"
pg_dump -d "$DB" --schema-only --no-owner --no-privileges --no-comments > "$here/_full_dump.sql"
python3 "$here/split.py"
