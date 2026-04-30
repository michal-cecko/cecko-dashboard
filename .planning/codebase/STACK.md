# Technology Stack

**Analysis Date:** 2025-04-30

## Languages

**Primary:**
- PHP 8.3.9 - Backend application runtime
- TypeScript/JavaScript - Frontend with Tailwind CSS v4

**Secondary:**
- SQL - PostgreSQL queries via Eloquent ORM

## Runtime

**Environment:**
- Laravel Sail (Docker) - Full containerized development and deployment environment
- PHP-FPM 8.5 (via Sail)
- Node.js - Frontend tooling

**Package Manager:**
- Composer 2.x - PHP dependency management
- npm 9+ - Node.js package management
- Lockfiles: `composer.lock`, `package-lock.json` (present)

## Frameworks

**Core:**
- Laravel Framework 13.7.0 - Backend web framework and routing
- Filament 5.1 - Admin panel and Server-Driven UI (SDUI) framework
- Livewire 4.1 - Reactive components without JavaScript

**Frontend:**
- Tailwind CSS 4.0.0 - Utility-first CSS framework
- Vite 6.2.4 - Build tool and development server
- Laravel Vite Plugin 1.2.0 - Vite integration for Laravel

**Testing:**
- PHPUnit 12.0 - PHP unit and feature testing
- Paratest 7.0 - Parallel test runner

**Development:**
- Laravel Pint 1.13 - PHP code formatter and style fixer
- Laravel Pail 1.2.2 - Real-time log monitoring
- Filament Blueprint 2.1 - Planning and code generation for Filament resources
- Laravel Boost 2.0 - Enhanced development tooling with MCP server

**Server/Performance:**
- Laravel Octane 2.15 - Performance optimization and long-running application support
- RoadRunner 3.3 (HTTP) + 2.7 (CLI) - High-performance application server

## Key Dependencies

**Critical:**
- `filament/filament` 5.1 - Admin UI framework built on Livewire and Alpine.js
- `livewire/livewire` 4.1 - Reactive PHP components for dynamic user interfaces
- `laravel/framework` 13.7.0 - Core framework providing routing, ORM, auth, validation
- `filament/spatie-laravel-media-library-plugin` 5.0 - Media/file management for Filament resources

**Infrastructure:**
- `sentry/sentry-laravel` 4.0 - Error tracking and performance monitoring (installed, not actively configured in sample)
- `league/flysystem-aws-s3-v3` 3.0 - AWS S3 filesystem driver for file storage
- `endroid/qr-code` 6.0 - QR code generation for invoices

**Development Tools:**
- `fakerphp/faker` 1.23 - Fake data generation for testing
- `mockery/mockery` 1.6 - Mocking library for tests
- `nunomaduro/collision` 8.6 - Exception error page for development
- `laravel/tinker` 3.0 - Interactive REPL for debugging

## Configuration

**Environment:**
- Uses `.env` file for environment-specific configuration
- `.env.example` provided as template with defaults
- Development: Local Sail containers with sensible defaults
- Key env vars: `APP_ENV`, `APP_DEBUG`, `DB_*`, `FILESYSTEM_DISK`, `QUEUE_CONNECTION`, `GOTENBERG_URL`, `EXCHANGE_RATE_API_URL`

**Build:**
- `tailwind.config.js` - Tailwind CSS configuration
- `vite.config.js` - Vite build configuration (implicit, uses Laravel Vite plugin)
- `jest.config.js` / `vitest.config.js` - Not present; uses PHPUnit only

## Platform Requirements

**Development:**
- Docker and Docker Compose
- PHP 8.3+ CLI for Artisan commands
- Node.js 18+ for frontend tooling
- PostgreSQL 15 (via Sail)
- Redis (via Sail, for caching/queues)

**Production:**
- PHP 8.3+ application server (Octane + RoadRunner recommended)
- PostgreSQL 15+ database
- Redis (optional, for queues and caching if not using database backend)
- Gotenberg service for PDF generation (via HTTP)
- AWS S3 (optional, for cloud file storage)
- External APIs: Czech National Bank (CNB) for exchange rates

## Services & External Dependencies

**From docker-compose.yml:**
- PostgreSQL 15 image on port 5432
- Redis Alpine image on port 6379
- Gotenberg 8 image on port 3000 (HTTP)

**Required External Services:**
- Czech National Bank API (`https://api.cnb.cz/cnbapi/exrates/daily`) - Exchange rate data
- Gotenberg HTTP service - PDF generation from HTML

---

*Stack analysis: 2025-04-30*
