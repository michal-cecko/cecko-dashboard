# Codebase Structure

**Analysis Date:** 2026-04-30

## Directory Layout

```
synapps-dashboard/
├── app/
│   ├── Casts/                  # Custom attribute casts (e.g., UserCapabilitiesCast)
│   ├── Console/
│   │   └── Commands/           # Artisan commands
│   ├── Enums/                  # Domain enums with translations
│   │   ├── Common/             # UserCapabilityEnum, CurrencyEnum, LocaleEnum, etc.
│   │   └── Invoices/           # InvoiceStatusEnum, PaymentMethodEnum, VatTypeEnum, etc.
│   ├── Filament/               # Filament panel UI configurations
│   │   ├── Common/             # Shared resources across panels
│   │   │   ├── Pages/
│   │   │   └── Resources/
│   │   │       ├── MobileApps/
│   │   │       └── Users/
│   │   ├── Invoices/           # Invoices panel (id: 'invoices', path: '/faktury')
│   │   │   ├── Components/     # Livewire components (CompanySwitcher)
│   │   │   ├── Concerns/       # Shared traits for this panel
│   │   │   ├── Pages/          # Custom pages (InvoiceDashboard)
│   │   │   ├── Resources/      # CRUD resources per model
│   │   │   │   ├── Companies/
│   │   │   │   ├── Customers/
│   │   │   │   ├── Invoices/   # InvoiceResource + Schemas/Form + Tables/Table + Pages + RelationManagers
│   │   │   │   ├── InvoicePayments/
│   │   │   │   ├── ServiceCatalogItems/
│   │   │   │   └── VatRates/
│   │   │   └── Widgets/        # Dashboard widgets (MonthlyIncomeChartWidget, etc.)
│   │   ├── Songs/              # Songs panel (id: 'songs', path: '/kniha-piesni')
│   │   │   └── Resources/
│   │   │       ├── SongArtists/
│   │   │       ├── Songs/
│   │   │       └── SongTags/
│   │   └── Toolkit/            # Toolkit panel
│   │       └── Resources/
│   │           └── Galleries/
│   ├── Http/
│   │   ├── Controllers/        # Non-Filament controllers
│   │   │   ├── Songs/
│   │   │   └── Toolkit/        # GalleryShareController for public gallery sharing
│   │   ├── Middleware/
│   │   │   ├── ApiMiddleware.php
│   │   │   ├── NoIndexHeaders.php
│   │   │   └── SetActiveCompany.php  # Multi-tenancy middleware
│   │   └── Resources/          # API Resources (if used)
│   ├── Mail/                   # Mailables (InvoiceMail)
│   ├── Models/                 # Eloquent models organized by domain
│   │   ├── Common/             # User, MobileApp, MobileAppVersion
│   │   ├── Invoices/           # Invoice, Company, Customer, InvoicePayment, etc.
│   │   ├── Songs/              # Song, SongArtist, SongTag, SongGenre
│   │   └── Toolkit/            # Gallery
│   ├── Policies/               # Authorization policies
│   │   ├── Common/
│   │   ├── Invoices/
│   │   └── Toolkit/
│   ├── Providers/              # Service providers
│   │   ├── AppServiceProvider.php
│   │   └── Filament/           # Panel providers
│   │       ├── InvoicesPanelProvider.php
│   │       ├── SongsPanelProvider.php
│   │       └── ToolkitPanelProvider.php
│   ├── Services/               # Business logic layer
│   │   ├── Invoices/           # InvoiceEmailService, InvoicePdfService, ExchangeRateService, etc.
│   │   └── Songs/              # ColorService
│   └── Traits/                 # Shared model behavior
│       ├── Common/             # EnumHelper (enum translation helper)
│       └── Invoices/           # BelongsToActiveCompany (multi-tenancy global scope)
├── bootstrap/
│   ├── app.php                 # Application configuration, middleware, routing
│   └── providers.php           # Service provider registration
├── database/
│   ├── factories/              # Model factories for testing
│   ├── migrations/             # Database schema migrations
│   └── seeders/                # Database seeders
├── lang/
│   ├── cs/                     # Czech translations
│   ├── en/                     # English translations
│   └── sk/                     # Slovak translations (primary)
│       ├── enums.php           # Enum translations
│       └── invoice.php         # Invoice-specific translations
├── resources/
│   ├── css/                    # Tailwind CSS entry point
│   ├── js/                     # JavaScript/Alpine.js
│   └── views/                  # Blade templates
│       ├── emails/             # Email templates
│       ├── filament/           # Filament-specific custom views
│       │   ├── common/
│       │   └── invoices/
│       │       └── components/ # Component views (company-switcher.blade.php)
│       ├── invoices/           # Invoice preview/PDF views
│       ├── toolkit/            # Public gallery views
│       └── vendor/             # Vendor package overrides
├── routes/
│   ├── api.php                 # API routes
│   ├── console.php             # Scheduled commands and Artisan closures
│   └── web.php                 # Web routes (non-Filament)
├── storage/
│   ├── app/                    # Application file storage
│   ├── logs/                   # Application logs
│   └── framework/              # Framework cache/views
├── tests/
│   ├── Feature/                # Feature tests (typically use database)
│   └── Unit/                   # Unit tests
├── config/                     # Configuration files
├── public/                     # Web-accessible assets
├── node_modules/               # npm dependencies
├── .env.example                # Example environment file
├── artisan                     # Artisan CLI
└── package.json                # npm and Node config
```

## Directory Purposes

**app/Filament/{PanelName}/Resources/{Model}/**
- Purpose: CRUD interface for a model
- Structure per resource:
  - `{Model}Resource.php` - Main resource class, delegates to Form/Table config
  - `Schemas/{Model}Form.php` - Static `configure(Schema)` method returning form schema
  - `Tables/{Model}Table.php` - Static `configure(Table)` method returning table schema
  - `Pages/` - List/Create/Edit/View page components
  - `RelationManagers/` - For managing related records inline

**app/Models/{Domain}/**
- Purpose: Eloquent model definitions with relationships and casts
- Naming: Model class name singular, matches table name (invoice → invoices table)
- Relationships: Defined as methods returning `BelongsTo`, `HasMany`, `BelongsToMany`
- Casts: Defined in `casts()` method (Laravel 12 style) for type safety

**app/Services/{Domain}/**
- Purpose: Encapsulate business logic, external API calls, calculations
- Pattern: Constructor-injected dependencies, public methods for actions
- No static methods; instantiate via DI container

**app/Traits/{Domain}/**
- Purpose: Reusable model behavior shared across multiple models
- Example: `BelongsToActiveCompany` applied to Invoice, Customer, ServiceCatalogItem, etc.

**lang/sk/**
- Purpose: Slovak translations for labels, messages, enum values
- `enums.php`: Enum translations keyed as `enums.{ClassName}.{VALUE}`
  - Example: `enums.App\Enums\Invoices\InvoiceStatusEnum.SENT` = 'Odoslané'

**database/migrations/**
- Purpose: Database schema version control
- Naming: Timestamped filename with descriptive name
- Running: `vendor/bin/sail artisan migrate` applies pending migrations

**database/factories/**
- Purpose: Generate fake test data
- Naming: `{Model}Factory.php`
- Usage: `Invoice::factory()->count(5)->create()` in tests/seeders

**tests/Feature/**
- Purpose: Integration tests exercising full request-response cycle
- Use database transactions (default in TestCase)
- Test authorization, validation, business logic together

**tests/Unit/**
- Purpose: Isolated unit tests for services, enums, models
- No database or HTTP context
- Test single class in isolation

## Key File Locations

**Entry Points:**
- `bootstrap/app.php` - Application bootstrap, middleware/routing config
- `bootstrap/providers.php` - Service provider list (AppServiceProvider, panel providers)
- `routes/web.php` - Non-Filament web routes (login, public gallery share, invoice preview)
- `routes/console.php` - Scheduled command definitions

**Configuration:**
- `config/app.php` - App name, locale, timezone
- `config/filesystems.php` - Storage disk configuration for media uploads
- `config/mail.php` - Mail driver, from address, reply-to
- `config/auth.php` - Authentication guards and providers
- `.env` - Environment-specific settings (never committed)

**Core Logic:**
- `app/Models/Common/User.php` - User model with company relationship, FilamentUser interface
- `app/Models/Invoices/Invoice.php` - Invoice model with BelongsToActiveCompany trait
- `app/Services/Invoices/InvoicePdfService.php` - PDF generation via Gotenberg
- `app/Services/Invoices/InvoiceEmailService.php` - Email sending with attachments
- `app/Enums/Common/UserCapabilityEnum.php` - Permission constants
- `app/Policies/Invoices/InvoicePolicy.php` - Authorization logic

**Testing:**
- `tests/TestCase.php` - Base test class with setup
- `tests/Feature/InvoicePolicyTest.php` - Policy authorization tests
- `tests/Feature/InvoiceModelTest.php` - Model relationship/scope tests
- `tests/Unit/InvoiceNumberServiceTest.php` - Service unit tests

## Naming Conventions

**Files:**
- Classes: `PascalCase`, one class per file, filename matches class name
  - Example: `class InvoiceResource` → `InvoiceResource.php`
- Migrations: `YYYY_MM_DD_HHMMSS_verb_object`, e.g., `2026_02_20_000009_create_invoices_table.php`
- Views: `snake_case.blade.php`, e.g., `company-switcher.blade.php`
- Factories: `{Model}Factory.php`
- Tests: `{Feature}Test.php` or `{Unit}Test.php`

**Directories:**
- Domain folders (Invoices, Songs, Common, Toolkit): PascalCase, plural when containing multiple resources
- Subfolders within resources: `Resources/`, `Pages/`, `Schemas/`, `Tables/`, `RelationManagers/`

**Classes & Functions:**
- Methods: `camelCase`
  - Actions: `verb + Noun`, e.g., `sendInvoice()`, `calculateTotal()`, `switchCompany()`
  - Relationships: `singularNoun()` or `pluralNoun()`, e.g., `company()`, `invoices()`, `payments()`
  - Queries: `scope{Name}()`, e.g., `scopeOverdue()`, or use global scopes via `addGlobalScope()`
- Properties: `camelCase` (public) or `private $property` with underscore prefix for internal state
- Constants: `UPPER_SNAKE_CASE` (primarily in Enums)

**Filament Components:**
- Resources: `{Model}Resource.php`
- Pages: `{Verb}{Model}.php`, e.g., `CreateInvoice`, `EditInvoice`, `ListInvoices`, `ViewInvoice`
- Widgets: `{Noun}Widget.php`, e.g., `MonthlyIncomeChartWidget`, `AmountsDueWidget`
- Components: `{Noun}Component.php` (if not Livewire) or inherit from `Component` class

**Enums:**
- Class name: `{Noun}Enum.php`, e.g., `InvoiceStatusEnum`, `PaymentMethodEnum`
- Case names: `UPPER_SNAKE_CASE`, e.g., `SENT`, `AFTER_DUE`, `PAID`
- Translations: `enums.{FullClassName}.{CASE_VALUE}` in `lang/sk/enums.php`

## Where to Add New Code

**New Feature (e.g., Invoice Reminders):**
- Primary code:
  - Model: `app/Models/Invoices/InvoiceReminder.php`
  - Migration: `database/migrations/{timestamp}_create_invoice_reminders_table.php`
  - Policy: `app/Policies/Invoices/InvoiceReminderPolicy.php`
  - Service: `app/Services/Invoices/InvoiceReminderService.php`
  - Resource: `app/Filament/Invoices/Resources/Reminders/ReminderResource.php`
- Tests: `tests/Feature/InvoiceReminderTest.php`
- Translations: `lang/sk/enums.php` (if adding enums), `lang/sk/invoice.php` (if adding labels)

**New Filament Resource (e.g., for existing model):**
- Main: `app/Filament/{Panel}/Resources/{Model}/{Model}Resource.php`
- Form config: `app/Filament/{Panel}/Resources/{Model}/Schemas/{Model}Form.php`
- Table config: `app/Filament/{Panel}/Resources/{Model}/Tables/{Model}Table.php`
- Pages: `app/Filament/{Panel}/Resources/{Model}/Pages/List{Model}.php`, `Create{Model}.php`, `Edit{Model}.php`, `View{Model}.php`
- Widgets: `app/Filament/{Panel}/Widgets/{Name}Widget.php`
- Register in panel provider: `discoverResources(in: app_path('Filament/{Panel}/Resources'), for: 'App\Filament\{Panel}\Resources')`

**New Service/Business Logic:**
- Implementation: `app/Services/{Domain}/{Feature}Service.php`
- Tests: `tests/Feature/{Feature}ServiceTest.php`
- Inject via constructor in calling code (Forms, Pages, Commands, Controllers)

**New Model & Migration:**
- Model: `app/Models/{Domain}/{Noun}.php`
- Migration: `database/migrations/{timestamp}_create_{table}_table.php`
- Factory: `database/factories/{Noun}Factory.php`
- Policy: `app/Policies/{Domain}/{Noun}Policy.php`
- Trait (if multi-tenant): `app/Traits/{Domain}/BelongsToActive{Parent}.php` (copy BelongsToActiveCompany pattern)

**New Enum:**
- Definition: `app/Enums/{Domain}/{Noun}Enum.php`
- Trait: Use `EnumHelper` trait
- Translations: Add entries to `lang/sk/enums.php` keyed as `enums.App\Enums\{Domain}\{Noun}Enum.{CASE_VALUE}`

**New Console Command:**
- Implementation: `app/Console/Commands/{VerbAdjective}Command.php`
- Register: Auto-discovered from `app/Console/Commands/`
- Schedule: Add to `routes/console.php` with `Schedule::command('command:name')->dailyAt('HH:MM')`

**New Livewire Component:**
- Implementation: `app/Livewire/{Feature}Component.php`
- View: `resources/views/livewire/{feature-component}.blade.php`
- Used in: Filament render hooks, custom pages, public views

**New Middleware:**
- Implementation: `app/Http/Middleware/{Name}Middleware.php`
- Register: Add to `bootstrap/app.php` in `->withMiddleware()` closure
  - Global: `$middleware->append()`
  - Web group: `$middleware->web(append: [])`
  - API group: `$middleware->api(append: [])`

## Special Directories

**storage/app/**
- Purpose: Application file storage (user uploads, generated PDFs, etc.)
- Generated: Yes (at runtime)
- Committed: No (gitignored)

**storage/logs/**
- Purpose: Application error/debug logs
- Generated: Yes (at runtime)
- Committed: No (gitignored)

**node_modules/**
- Purpose: JavaScript/npm dependencies
- Generated: Yes (`npm install`)
- Committed: No (gitignored)

**lang/{locale}/enums.php**
- Purpose: Enum value translations for all enums
- Manually maintained: Yes
- Pattern: `'App\Enums\Common\UserCapabilityEnum' => ['VIEW_INVOICES' => 'Zobraziť faktúry']`

---

*Structure analysis: 2026-04-30*
