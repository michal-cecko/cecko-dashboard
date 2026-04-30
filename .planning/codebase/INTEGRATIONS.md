# External Integrations

**Analysis Date:** 2025-04-30

## APIs & External Services

**PDF Generation:**
- Gotenberg - HTML to PDF converter
  - SDK/Client: HTTP client via `Illuminate\Support\Facades\Http`
  - Config: `config('services.gotenberg.url')` - defaults to `http://gotenberg:3000`
  - Location: `app/Services/Invoices/InvoicePdfService.php`
  - Endpoint: `POST /forms/chromium/convert/html`
  - Use case: Invoice PDF generation with custom page dimensions and margins

**Exchange Rates:**
- Czech National Bank (ČNB) API
  - URL: `https://api.cnb.cz/cnbapi/exrates/daily`
  - SDK/Client: HTTP client via `Illuminate\Support\Facades\Http`
  - Use case: Daily currency exchange rate retrieval
  - Data caching: Stored in `exchange_rates` table
  - Location: `app/Services/Invoices/ExchangeRateService.php`
  - No authentication required

**Error Tracking (Configured but not actively used in sample):**
- Sentry - Error tracking and performance monitoring
  - Package: `sentry/sentry-laravel` 4.0
  - Config: `config/sentry.php` (default Laravel Sentry config)
  - DSN: Environment variable `SENTRY_LARAVEL_DSN` (not set in .env.example)

## Data Storage

**Databases:**
- PostgreSQL 15
  - Connection: `pgsql` connection in `config/database.php`
  - Host: `pgsql` (via Docker/Sail)
  - Port: 5432
  - Client: Eloquent ORM with Laravel's query builder
  - Auth: `DB_USERNAME` / `DB_PASSWORD` env vars
  - Tables: migrations, invoices, customers, companies, users, exchange_rates, galleries, media, etc.

**File Storage:**
- **Local filesystem** (default)
  - Private disk: `storage/app/private` - Non-public files
  - Public disk: `storage/app/public` - Publicly accessible via `/storage` route
  - Served via symbolic link: `public/storage` → `storage/app/public`
  - Config: `config/filesystems.php`

- **AWS S3** (optional)
  - Public disk can be S3: Controlled by `PUBLIC_DISK_DRIVER` env var
  - Private disk can be S3: Controlled by `PRIVATE_DISK_DRIVER` env var
  - Requires: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`
  - Package: `league/flysystem-aws-s3-v3` 3.0

**Caching:**
- Database-backed cache (default)
  - Driver: `database`
  - Store: `CACHE_STORE` env var (defaults to `database`)
  - Table: `cache` and `cache_locks`
  
- Redis cache (available)
  - Connection: `redis` in `config/database.php`
  - Host: `redis` (via Docker/Sail)
  - Port: 6379
  - Optional: `REDIS_PASSWORD` for authentication

## Authentication & Identity

**Auth Provider:**
- Laravel's built-in authentication
  - Guard: Session-based (`web` guard in `config/auth.php`)
  - Provider: Eloquent (User model in `App\Models\Common\User`)
  - Password reset table: `password_reset_tokens`
  - Bcrypt rounds: 12 (configurable via `BCRYPT_ROUNDS` env var)

**Filament Integration:**
- Filament panels use built-in authentication
- Multiple panels: `SongsPanelProvider`, `InvoicesPanelProvider`, `ToolkitPanelProvider` (registered in `bootstrap/providers.php`)
- Authorization: Laravel's gate and policy system
- API Basic Auth (optional): Credentials in `config/auth.php` under `api_basic_auth`

## Monitoring & Observability

**Error Tracking:**
- Sentry integration available via `sentry/sentry-laravel` 4.0
- Status: Configured in composer.json but DSN not set in provided .env.example

**Logs:**
- Monolog-based logging via Laravel's logging facade
- Default: Single file log to `storage/logs/laravel.log`
- Config: `config/logging.php`
- Channels available:
  - `single` - Single file
  - `daily` - Daily log rotation
  - `slack` - Slack webhook logging (requires `LOG_SLACK_WEBHOOK_URL`)
  - `papertrail` - Syslog to Papertrail (requires `PAPERTRAIL_URL`, `PAPERTRAIL_PORT`)
  - `stderr` - Standard error output
- Pail integration: `vendor/bin/sail artisan pail` for real-time log streaming

## Queue & Jobs

**Default Queue Driver:**
- Database queue (default)
  - Connection: `database`
  - Table: `jobs`
  - Config: `config/queue.php`
  - Retry after: 90 seconds
  - Failed jobs: `failed_jobs` table with UUID tracking

**Alternative Drivers (Configured):**
- Sync - Synchronous (execute immediately)
- Redis - via Redis driver
- Beanstalkd - Job queue server
- AWS SQS - Simple Queue Service

**Execution:**
- Development: `vendor/bin/sail artisan queue:listen --tries=1`
- Failed job tracking: `queue:failed` command to retry

## Scheduled Tasks

**Scheduled Commands (in `routes/console.php`):**
1. `invoices:check-overdue` - Daily at 00:45 UTC
   - Location: `app/Console/Commands/CheckOverdueInvoicesCommand.php`
   - Updates invoice status to AFTER_DUE when past due_date

2. `invoices:fetch-exchange-rates` - Daily at 00:30 UTC
   - Location: `app/Console/Commands/FetchExchangeRatesCommand.php`
   - Fetches and caches common exchange rates (EUR, USD, GBP, PLN, HUF vs CZK)

3. `toolkit:delete-expired-galleries` - Daily at 01:00 UTC
   - Location: `app/Console/Commands/Toolkit/DeleteExpiredGalleries.php`
   - Deletes galleries past their expiration date (media gallery feature)

**Scheduler:**
- Linux cron job: Single entry `* * * * * cd /project && php artisan schedule:run >> /dev/null 2>&1`
- Or via Docker: Sail handles scheduler automatically

## Mail

**Default Mailer:**
- Log driver (development default)
  - Driver: `log`
  - Channel: `MAIL_LOG_CHANNEL` env var

**Configured Mailers:**
- SMTP - `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`
- SES (AWS) - via AWS credentials
- Postmark - via `POSTMARK_TOKEN`
- Resend - via `RESEND_KEY`
- Sendmail - via `/usr/sbin/sendmail`
- Failover - SMTP then log
- Roundrobin - SES and Postmark rotation

**From Address:**
- `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` env vars

## Session Management

**Session Driver:**
- Database-backed sessions (default)
  - Table: `sessions`
  - Lifetime: 120 minutes (configurable via `SESSION_LIFETIME`)
  - Encryption: Disabled by default (`SESSION_ENCRYPT=false`)
  - Path: `/` (root)

## Webhooks & Callbacks

**Incoming:**
- API routes in `routes/api.php` (not examined in detail)
- Basic auth protection available via `config/auth.php`

**Outgoing:**
- Not detected in provided configuration
- Queue system available for background webhook delivery if needed

## Environment Variables Summary

**Critical for Setup:**
- `APP_KEY` - Application encryption key
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `GOTENBERG_URL` - PDF generation service
- `FILESYSTEM_DISK` - Default storage disk (local/s3)
- `QUEUE_CONNECTION` - Queue driver (database/redis/sync)

**Optional for Cloud Storage:**
- `PUBLIC_DISK_DRIVER`, `PRIVATE_DISK_DRIVER` - Set to `s3` for cloud
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_DEFAULT_REGION`

**For Integrations:**
- `MAIL_MAILER` - Email service
- `SENTRY_LARAVEL_DSN` - Error tracking
- `LOG_SLACK_WEBHOOK_URL` - Slack logging
- `EXCHANGE_RATE_API_KEY` - Reserved for future use (currently unused)

---

*Integration audit: 2025-04-30*
