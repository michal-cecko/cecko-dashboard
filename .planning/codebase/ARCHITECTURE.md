# Architecture

**Analysis Date:** 2026-04-30

## Pattern Overview

**Overall:** Multi-panel Filament application with domain-driven directory structure and multi-tenancy via active company scoping.

**Key Characteristics:**
- Multiple independent Filament panels (Songs, Invoices, Toolkit) registered in `bootstrap/providers.php`
- Eloquent models organized by domain (Invoices, Songs, Common, Toolkit)
- Resource pattern: `Resource.php` + separate `Schemas/Form.php` and `Tables/Table.php` configuration classes
- Multi-tenancy via `SetActiveCompany` middleware + `BelongsToActiveCompany` trait with global scoping
- Capability-based authorization via `UserCapabilityEnum` and policies
- Service layer for business logic (InvoiceEmailService, InvoicePdfService, ExchangeRateService, etc.)
- Console commands scheduled for automated tasks (exchange rates, overdue invoices, gallery cleanup)

## Layers

**Presentation (Filament Panels):**
- Purpose: Server-driven UI built with Filament v5, rendering forms, tables, widgets, and pages
- Location: `app/Filament/{PanelName}/Resources/`, `Pages/`, `Widgets/`, `Components/`
- Contains: Resources, Forms, Tables, Pages, Widgets, Livewire components
- Depends on: Models, Policies, Services, Enums
- Used by: End users via web browser

**Business Logic (Service Layer):**
- Purpose: Encapsulate complex domain logic, calculations, external integrations
- Location: `app/Services/{Domain}/`
- Contains: Services like `InvoiceEmailService`, `InvoicePdfService`, `ExchangeRateService`, `InvoiceNumberService`, `InvoiceCalculationService`, `PayBySquareService`
- Depends on: Models, Enums, external APIs (Gotenberg, exchangerate.host)
- Used by: Controllers, Livewire components, Commands, Form actions

**Data Access (Models & Traits):**
- Purpose: Eloquent models represent database entities; traits provide shared behavior
- Location: `app/Models/{Domain}/`, `app/Traits/{Domain}/`
- Contains: Models (Invoice, Company, Customer, Gallery, Song, etc.) with relationships and casts
- Depends on: Database schema, Laravel Eloquent
- Used by: All layers (Services, Policies, Filament, Controllers)

**Authorization (Policies):**
- Purpose: Centralized policy logic for authorization decisions
- Location: `app/Policies/{Domain}/`
- Contains: Policies that check `UserCapabilityEnum` capabilities (VIEW_INVOICES, MANAGE_INVOICES, VIEW_ALL_INVOICES, etc.)
- Depends on: User model, Models being authorized
- Used by: Filament form/table visibility checks, Controllers

**Configuration & Middleware:**
- Purpose: Application-wide setup and request handling
- Location: `bootstrap/app.php`, `bootstrap/providers.php`, `app/Http/Middleware/`
- Contains: Middleware setup, panel provider registration, routing configuration
- Depends on: Filament, Laravel framework
- Used by: Framework during request lifecycle

## Data Flow

**Typical User Request (Invoices Panel):**

1. User navigates to `/faktury` (Invoices panel)
2. Filament middleware chain executes:
   - `Authenticate::class` - Ensures user logged in
   - `SetActiveCompany::class` - Sets `user->active_company_id` if not set, validates ownership
3. Filament panel loads resource (e.g., InvoiceResource)
4. Resource form/table configuration loads via `InvoiceForm::configure()` and `InvoicesTable::configure()`
5. Model queries automatically apply `BelongsToActiveCompany` global scope, filtering by active company
6. Table displays invoices for active company only
7. User submits form or triggers action
8. Form validation runs (if using Form Request validation)
9. Policy check occurs (e.g., `InvoicePolicy@update`)
10. Service layer executes business logic (e.g., `InvoiceCalculationService`, `InvoicePdfService`)
11. Model mutations trigger, data persisted to database
12. Response returned to user

**Company Switching Flow:**

1. User clicks company selector (CompanySwitcher Livewire component in sidebar)
2. `switchCompany()` action in `CompanySwitcher` validates company ownership
3. User model's `active_company_id` updated
4. Page redirects to current panel home
5. All subsequent queries scoped to new active company

**Invoice Email Sending:**

1. User clicks "Send Email" action on invoice row
2. Modal form collects email, subject, body
3. `InvoiceEmailService::sendInvoice()` called
4. `InvoicePdfService::generatePdf()` generates PDF via Gotenberg
5. Email attachments stored to disk (Storage facade with configured disk)
6. `InvoiceMail` mailable queued or sent via Mail facade
7. `InvoiceEmailLog` created to track send history

## Key Abstractions

**Filament Panel:**
- Purpose: Separate admin interfaces per domain
- Examples: `InvoicesPanelProvider` (path: `/faktury`), `SongsPanelProvider` (path: `/kniha-piesni`), `ToolkitPanelProvider`
- Pattern: Each extends `PanelProvider`, registers resources/pages/widgets, configures middleware stack

**Filament Resource:**
- Purpose: CRUD interface for a model
- Examples: `InvoiceResource`, `CustomerResource`, `SongResource`, `GalleryResource`
- Pattern: Delegates form/table config to separate `Schemas/{Name}Form.php` and `Tables/{Name}Table.php` classes

**Service (Business Logic):**
- Purpose: Reusable domain logic, external integrations
- Examples: `InvoiceEmailService`, `InvoicePdfService`, `ExchangeRateService`
- Pattern: Injected via constructor, encapsulates complex logic, returns domain objects or void

**Enum with Helper:**
- Purpose: Type-safe enumeration of finite values with translations
- Examples: `UserCapabilityEnum`, `InvoiceStatusEnum`, `PaymentMethodEnum`, `CurrencyEnum`
- Pattern: Uses `EnumHelper` trait; translations keyed in `lang/sk/enums.php` as `enums.{ClassName}.{VALUE}`

**Global Scope (BelongsToActiveCompany):**
- Purpose: Automatic filtering of multi-tenant data
- Pattern: Trait applied to models; boots static scope that filters by `company_id = auth()->user()->active_company_id`; also auto-fills `company_id` on create

**Filament Action:**
- Purpose: Button + optional modal form + business logic
- Examples: Send Email action, Delete action, Duplicate Invoice action
- Pattern: Built with `Action::make()`, optional `->form([])`, `->action()` closure

## Entry Points

**Web Routes:**
- Location: `routes/web.php`
- Purpose: Public/authenticated routes not in Filament panels
- Examples: Invoice PDF preview (`/faktury/preview/{invoice}`), Public gallery share (`/gallery/{token}`)

**API Routes:**
- Location: `routes/api.php`
- Purpose: API endpoints (if used)
- Middleware: `ApiMiddleware`

**Console Routes (Scheduled Commands):**
- Location: `routes/console.php`
- Purpose: Task scheduling
- Commands:
  - `invoices:check-overdue` - Daily at 00:45, marks past-due invoices
  - `invoices:fetch-exchange-rates` - Daily at 00:30, updates exchange rates from API
  - `toolkit:delete-expired-galleries` - Daily at 01:00, deletes expired galleries with auto-delete flag

**Filament Panel Entry Points:**
- Songs: `filament.songs.home` → `/kniha-piesni`
- Invoices: `filament.invoices.home` → `/faktury`
- Toolkit: `filament.toolkit.home` → `/toolkit`

## Error Handling

**Strategy:** Default Laravel exception handling; no custom global handler configured in `bootstrap/app.php`.

**Patterns:**
- Authorization failures: `abort_unless(auth()->user()->can('view', $invoice), 403)` in controllers
- Model not found: Eloquent automatically throws `ModelNotFoundException` (renders 404)
- Validation errors: Form validation in Filament forms displays field-level errors
- Gallery access: `GalleryShareController` returns 410 (Gone) for expired galleries

## Cross-Cutting Concerns

**Authentication:** Laravel's built-in authentication + Filament's `Authenticate` middleware per panel.

**Authorization:** 
- Model-level: Policies (`app/Policies/`) check `UserCapabilityEnum`
- Filament-level: Form/Table visibility/hiddenness controlled via `->visible()`, `->hidden()` closures calling policies
- Special case: `VIEW_ALL_INVOICES` capability bypasses company scoping (admin override)

**Logging:** Default Laravel logging to file/stdout; `InvoiceEmailLog` model explicitly tracks email sends.

**Validation:** 
- Filament form validation via form field definitions
- Server-side can use Form Request classes (if created) or inline validation
- Custom validation messages in Slovak

**Multi-Tenancy:** 
- Achieved via `SetActiveCompany` middleware + `BelongsToActiveCompany` trait
- Company context stored in `user->active_company_id`
- Automatic query scoping; users can only see/edit their own company's data (unless `VIEW_ALL_INVOICES`)

**Media Handling:**
- Spatie Media Library integration on User, Company, Gallery models
- Collections: User avatar, Company logo/signature, Gallery media
- Disk configuration: Uses Storage facade (configurable in `config/filesystems.php`)

**Email:**
- `InvoiceEmailService` generates PDF, stores attachments, sends via Mail facade
- `InvoiceMail` mailable with HTML/text views in `resources/views/emails/`
- Reply-to configured via `config/mail.reply_to` event listener in `AppServiceProvider`

**Exchange Rates:**
- `ExchangeRateService` fetches from exchangerate.host API
- Cached in database table
- Scheduled command updates daily
- Used in invoice calculations for currency conversion

---

*Architecture analysis: 2026-04-30*
