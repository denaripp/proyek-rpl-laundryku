# Tahap 21 — Database Backup & Restore

## Strategy

- Daily automatic backup via scheduler (cron / Task Scheduler).
- Retention: last 7 daily + 4 weekly backups.
- Storage: `storage/app/backups/` on the same volume (for MVP). For
  production, copy off-site to S3 / B2 daily (TODO before scale).

## Backup Command

```bash
php artisan db:backup
# default: storage/app/backups/backup-<dbname>-<YYYYMMDD-HHMMSS>.sql

php artisan db:backup --output=/path/to/backup.sql --keep=7
```

Reads MySQL credentials from `.env` (`DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD`). Requires `mysqldump` on PATH.

## Restore

```bash
# 1. Identify which backup to restore
ls -lh storage/app/backups/

# 2. Restore (overwrites current database — destructive!)
mysql --host=$DB_HOST --user=$DB_USERNAME --password=$DB_PASSWORD \
      $DB_DATABASE < storage/app/backups/backup-laundryku-20260902-020000.sql
```

Or via PHP script (uses .env credentials):

```bash
php artisan tinker --execute="
\\Symfony\\Component\\Process\\Process::fromShellCommandline(
  sprintf('mysql --host=%s --user=%s --password=%s %s < %s',
    config('database.connections.mysql.host'),
    config('database.connections.mysql.username'),
    config('database.connections.mysql.password'),
    config('database.connections.mysql.database'),
    storage_path('app/backups/backup-laundryku-LATEST.sql')
  )
)->mustRun();
"
```

## Scheduler Setup (Cron)

Add to crontab on production server:

```cron
# Daily backup at 02:00 (server local time)
0 2 * * * cd /var/www/laundryku && php artisan db:backup --keep=7 >> /var/log/laundryku/backup.log 2>&1

# Hourly app health snapshot (queued job)
0 * * * * cd /var/www/laundryku && php artisan schedule:run >> /dev/null 2>&1
```

For Laragon / Windows Task Scheduler equivalent: see ops notes.

## Verification

After every backup, restore to a temporary database and run:

```bash
mysqldump ... > backup.sql
mysql -e "CREATE DATABASE laundryku_restore;"
mysql laundryku_restore < backup.sql
php artisan migrate --pretend    # should report NO pending migrations
php artisan test --testsuite=Smoke
```

## Recovery Time Objective (RTO)

- Backups are full SQL dumps, no incremental.
- Restore time: ~5 min for database under 1 GB on local SSD.
- For larger datasets, switch to Percona XtraBackup (binary) — TODO.

## Security

- Backup files contain PII (user emails, addresses). Encryption at rest
  on backup storage is REQUIRED in production (filesystem-level LUKS
  / EBS encryption).
- Never email or upload backups to public S3 buckets.
- Quarterly test restore to a sandbox environment to verify integrity.