# Synapps Dashboard

Multi-panel internal toolkit for [Synapps s.r.o.](https://synapps.sk). Each domain runs as its own Filament v5 panel, sharing a common user / auth layer.

## Panels

| Panel | What it does |
|---|---|
| **Garaz** | Personal/fleet vehicle maintenance log — bicycles, cars, motorcycles. Concern tracking, assessment checks, maintenance reminders, knowledge notes. |
| **Invoices** | Multi-company invoicing — clients, companies, customers, line items, payment methods, multi-currency exchange rates, email logs. |
| **Songs** | Personal song collection — artists, genres, tags. |
| **Toolkit** | Misc utilities (galleries, file-share, etc.). |

Plus a **Common** layer for users, API tokens, and the in-house mobile app registry (`MobileApp` + `MobileAppVersion`).

## Stack

| Layer | Tech |
|---|---|
| Backend | **Laravel 12** on PHP 8.3, **Octane** + RoadRunner |
| Admin | **Filament v5** (4 separate panels) |
| Media | Spatie MediaLibrary |
| Errors | Sentry |
| Tests | PHPUnit + ParaTest |
| Code style | Laravel Pint |
| Deploy | Docker → Dokploy |

## Local dev

```bash
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail composer install
vendor/bin/sail npm install
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate --seed
vendor/bin/sail npm run dev
```

Each panel mounts at its own path: `/garaz`, `/invoices`, `/songs`, `/toolkit`.

## Required env

| Var | Purpose |
|---|---|
| `COMPOSER_AUTH` | Filament Pro auth (build time) |
| `SEED_USER_PASSWORD` | Initial seeder admin password (random if unset) |

## CI

GitHub Actions `ci.yml`:

1. **test** — Pint + PHPUnit against Postgres service
2. **deploy-worker** — Dokploy API redeploy
3. **notify** — Telegram on failure

## Deploy

Two-stage `Dockerfile`. Build stage uses a pre-built base image. Runtime is lean PHP 8.4 alpine + Octane.

## License

[MIT](LICENSE) © Michal Čečko
