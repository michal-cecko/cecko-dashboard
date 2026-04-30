# Codebase Concerns

**Analysis Date:** 2026-04-30

## Tech Debt

**Large Filament Form/Table Classes:**
- Issue: `InvoiceForm.php` (483 lines) and `InvoicesTable.php` (398 lines) have exceeded reasonable maintainability thresholds and contain complex nested form logic, making modifications risky
- Files: `app/Filament/Invoices/Resources/Invoices/Schemas/InvoiceForm.php`, `app/Filament/Invoices/Resources/Invoices/Tables/InvoicesTable.php`
- Impact: Single-page changes require understanding 400+ line contexts; refactoring or adding vehicle module features will require careful coordination; code review burden is high
- Fix approach: Extract form sections into dedicated schema classes (`InvoiceBasicsSection`, `InvoiceItemsSection`, `InvoiceTextSection`); extract table columns and actions into builder classes for reusability

**Edit Page Complexity:**
- Issue: `EditInvoice.php` (221 lines) and `ViewInvoice.php` (213 lines) pages manage heavy custom logic for invoice state transitions, PDF preview, and email workflows in page classes
- Files: `app/Filament/Invoices/Resources/Invoices/Pages/EditInvoice.php`, `app/Filament/Invoices/Resources/Invoices/Pages/ViewInvoice.php`
- Impact: Business logic is tangled with Filament page concerns; changes to invoice workflow require page modification; difficult to test independent of Filament
- Fix approach: Extract workflows into dedicated action/state machine classes; use Livewire component abstractions to isolate concerns

**No Polymorphic Media Relationships:**
- Issue: Media gallery (`Gallery` model) only implements Spatie media library on the Gallery model itself; no generic polymorphic media attachment pattern exists for other models (invoices, companies, future vehicles)
- Files: `app/Models/Toolkit/Gallery.php`
- Impact: When vehicle module is added, media attachment logic will be duplicated; no reusable media handling pattern; limits flexibility for adding media to different entity types
- Fix approach: Create a generic `HasAttachableMedia` trait or abstract base class that provides polymorphic media handling; implement on Gallery, then adapt for Invoice, Company, Vehicle models

**No Type-Aware Sub-Model Pattern for Vehicle Module:**
- Issue: Invoice module has deeply nested type-specific logic (VAT types, payment methods) but no reusable pattern for polymorphic entity subtypes; vehicle module will need specs (engine, transmission, maintenance), which requires building this pattern from scratch
- Files: Scattered across `app/Models/Invoices/*`, `app/Enums/Invoices/*`, `app/Filament/Invoices/Schemas/InvoiceForm.php`
- Impact: Vehicle features will require significant custom implementation; duplicate type-handling logic; risk of inconsistent patterns across modules
- Fix approach: Establish a standard trait/pattern for "type-aware models with type-specific attributes" (possibly using JSON columns with validation schemas)

**Unplanned Queue Worker Dependency:**
- Issue: Application uses database queue driver by default (see `config/queue.php`), media library conversions are queued (`queue_conversions_by_default: true`), but there is no documented queue worker deployment requirement in docker/deployment configs
- Files: `config/queue.php`, `config/media-library.php`, `.env.example`
- Impact: In production, queued image conversions won't process without an active worker; media uploads appear to succeed but images never generate; soft failure difficult to diagnose
- Fix approach: Clarify queue worker requirements in deployment documentation; consider sync driver for personal-scale app or add worker container to docker-compose

## Security Considerations

**Public Gallery Share Token in URL Without Rate Limiting:**
- Risk: Gallery share tokens are UUIDs (strong) but are exposed in URL; public endpoint has no rate limiting, allowing enumeration attacks or brute-force attempts to discover galleries
- Files: `routes/web.php` (line 20-22), `app/Http/Controllers/Toolkit/GalleryShareController.php`
- Current mitigation: UUID format reduces collision likelihood; `isAccessible()` check prevents accessing expired galleries
- Recommendations: Add rate limiting middleware to public gallery routes; consider IP whitelisting or optional password protection for sensitive galleries; log access attempts to detect anomalies

**File Upload Disk Configuration Ambiguity:**
- Risk: `config/filesystems.php` supports both local and S3 storage with conditional switching, but tests and examples only use local disk; production S3 configuration untested and error handling incomplete
- Files: `config/filesystems.php`, `app/Services/Invoices/InvoiceEmailService.php` (line 75 uses 'private' disk)
- Current mitigation: Error reporting set to false in both disk configs, masking real issues
- Recommendations: Test S3 upload/download flows in staging; ensure disk errors are logged (set `'report' => true`); validate AWS credentials are non-empty in production; add explicit disk selection for invoice attachments

**No File Type Validation for Email Attachments:**
- Risk: `InvoiceEmailService::storeAttachments()` accepts user-uploaded files without mime type or extension validation
- Files: `app/Services/Invoices/InvoiceEmailService.php` (line 70-85)
- Current mitigation: Files are stored in private disk, reducing exposure
- Recommendations: Add file type whitelist (PDF, images, documents only); validate mime types server-side; limit file size explicitly; scan for malicious content

**Public Endpoint Missing CSRF Protection:**
- Risk: Gallery public endpoint (`route('gallery.public')`) has no middleware and is intentionally unauthenticated, but file downloads from galleries are served without validation
- Files: `routes/web.php` (line 20-22)
- Current mitigation: Galleries must be explicitly activated and have share token; access logs not implemented
- Recommendations: Add access logging to public gallery views; implement rate limiting; optionally add IP-based access control

**Hardcoded APP_KEY in .env.example:**
- Risk: `.env.example` includes a real APP_KEY value, creating risk if example is exposed or used directly
- Files: `.env.example` (line 3)
- Current mitigation: File is in version control and marked as example
- Recommendations: Replace with placeholder like `base64:CHANGE_ME_IN_PRODUCTION`; add pre-deployment checklist

**No API Authentication for Invoice Preview:**
- Risk: Invoice preview endpoint requires auth but generates arbitrary HTML; HTML could contain XSS if user-controlled content is unescaped in PDF templates
- Files: `routes/web.php` (line 12-18), `app/Services/Invoices/InvoicePdfService.php` (line 18-71)
- Current mitigation: Endpoint requires authentication; template uses Laravel's escaping
- Recommendations: Audit all user-controlled fields in PDF template for XSS; sanitize rich text fields; document trusted vs. untrusted input

## Performance Bottlenecks

**N+1 Query Risk in Invoices Table:**
- Problem: `InvoicesTable.php` displays customer name via `customer.name` column without explicit eager loading; when rendering 25 invoices per page, queries customer for each row
- Files: `app/Filament/Invoices/Resources/Invoices/Tables/InvoicesTable.php` (line 54-57)
- Cause: Filament tables apply `->searchable()` on relations without automatic eager loading; no `modifyQueryUsing()` hook to add `with('customer')`
- Improvement path: Add eager loading in InvoiceResource or via a `modifyQueryUsing()` callback in table configuration

**Missing Eager Loading in Form Field Options:**
- Problem: Service catalog dropdown loads all items `with('translations')` on every form interaction (line 303), and again with `['translations', 'defaultVatRate']` on field update (line 310)
- Files: `app/Filament/Invoices/Resources/Invoices/Schemas/InvoiceForm.php` (line 303, 310)
- Cause: Eager loading is correct but duplicated; field live update re-queries unnecessarily
- Improvement path: Cache service catalog items or memoize the query; consider lazy-loading with Alpine

**Large PDF Generation Synchronously:**
- Problem: `InvoicePdfService::generatePdf()` makes HTTP request to Gotenberg service synchronously; if Gotenberg is slow or unresponsive, request blocks UI
- Files: `app/Services/Invoices/InvoicePdfService.php` (line 73-95)
- Cause: PDF generation is I/O bound and can take 2-5 seconds; no timeout configured on HTTP request
- Improvement path: Queue PDF generation as a background job; add HTTP timeout (currently defaults to 30s); implement progress indicator for user

**Image Conversion Queue Not Worker-Backed in Default Config:**
- Problem: Media library queues conversions but uses database queue with no worker, causing responsive image generation to be delayed indefinitely
- Files: `config/media-library.php` (line 47, 58), `config/queue.php` (line 16)
- Cause: Default queue connection is 'database' but application has no documented queue worker
- Improvement path: Document queue worker requirement; provide docker worker service; or switch to sync queue for local development

**Invoice Dashboard Widgets May Query Inefficiently:**
- Problem: Dashboard widgets (`MonthlyIncomeChartWidget.php`, `InvoicesByStatusWidget.php`, etc.) likely execute multiple queries to fetch chart data; no pagination or aggregation optimization visible
- Files: `app/Filament/Invoices/Widgets/*`
- Cause: Widget implementation not reviewed for query efficiency
- Improvement path: Profile widget queries with Laravel Debugbar; add eager loading; consider caching for chart data; use database aggregations instead of model iteration

## Fragile Areas

**Invoice Calculation Service Complexity:**
- Files: `app/Services/Invoices/InvoiceCalculationService.php`
- Why fragile: VAT calculation has multiple conditional branches (standard, reverse charge, no VAT) that can silently produce incorrect totals if exchange rates or item types are misconfigured
- Safe modification: Add comprehensive property-based tests for VAT combinations; validate snapshots against actual items before saving; log calculation inputs/outputs
- Test coverage: 250 lines of feature tests exist but edge cases (multi-currency, negative amounts, zero VAT) need explicit coverage

**PayBySquareService QR Code Generation:**
- Files: `app/Services/Invoices/PayBySquareService.php` (230 lines)
- Why fragile: Generates QR codes for bank transfers; incorrect format could produce invalid QR codes that fail on payment processing
- Safe modification: Add unit tests for QR code format; validate against PayBySquare documentation; capture QR code strings in snapshots to detect format changes
- Test coverage: 309 lines of tests exist but QR validation is limited

**Company Signature and Logo File Handling:**
- Files: `app/Models/Invoices/Company.php`
- Why fragile: `getSignatureBase64()` and `getLogoBase64()` methods load files from storage without explicit path validation; missing files raise exceptions or return null silently
- Safe modification: Add validation that files exist before reading; provide default fallbacks; log file access errors; use Laravel's Storage facade consistently
- Test coverage: No explicit tests for file handling; invoice rendering tests likely hide file errors

**Gallery Expiration Logic:**
- Files: `app/Models/Toolkit/Gallery.php` (line 70-78), `app/Console/Commands/Toolkit/DeleteExpiredGalleries.php`
- Why fragile: Expiration check uses `isPast()` which depends on server timezone; deletion command runs manually without monitoring
- Safe modification: Store expiration timestamps in UTC; add logging to deletion command; consider scheduled job monitoring; test timezone edge cases
- Test coverage: Tests exist for expiration (72 lines) but timezone edge cases not covered

**InvoiceEmailLog Attachment Storage:**
- Files: `app/Models/Invoices/InvoiceEmailLog.php` (array cast for attachments), `app/Services/Invoices/InvoiceEmailService.php` (line 70-85)
- Why fragile: Stores attachment metadata as JSON array but actual files are stored separately; orphaned files can accumulate if email logs are deleted without cleanup
- Safe modification: Add foreign key or lifecycle hook to clean up stored attachments when logs are deleted; validate file paths exist; document attachment retention policy
- Test coverage: Email service tests (126 lines) exist but attachment cleanup not tested

## Scaling Limits

**Media Library Conversions at Scale:**
- Current capacity: Application configured for 100MB max file size; image optimizers process single files sequentially
- Limit: Multiple concurrent uploads with image conversion will queue up; no batch processing or parallel conversion
- Scaling path: Implement batch job processing; use job-pooling; consider external image service (Cloudinary) for high volume; monitor queue depth

**Invoice Dashboard Widget Refresh:**
- Current capacity: Widgets recalculate on page load; no caching strategy visible
- Limit: With 1000+ invoices, dashboard load could exceed 5-10 seconds; widget queries will N+1 without eager loading
- Scaling path: Cache widget data with 1-hour TTL; add background refresh job; use database aggregations; implement pagination for chart data

**Database Queue Scaling:**
- Current capacity: `jobs` table used for all queued work; no partitioning or archival
- Limit: Failed/completed jobs accumulate indefinitely (see `failed_jobs` table); jobs table will grow unbounded
- Scaling path: Implement job pruning/archival; switch to Redis queue for production; add monitoring for queue depth

**Company-Based Data Isolation:**
- Current capacity: `BelongsToActiveCompany` trait filters queries by active company; works for single company per user
- Limit: If user is in multiple companies, switching context requires manual query filtering; no built-in per-company authorization boundaries
- Scaling path: Implement role-based access control (RBAC) per company; add audit logging for cross-company access; validate company context on every request

## Missing Critical Features

**Queue Worker Monitoring:**
- Problem: No dashboard or alerting for queue health; if worker crashes, jobs silently fail
- Blocks: Image processing, PDF generation, mail sending all depend on queues
- Solution: Implement queue monitoring dashboard; add alerts for queue depth; set up worker process monitor (Supervisor/Docker)

**Media Attachment for Arbitrary Models:**
- Problem: Gallery implementation doesn't support attaching media to invoices, companies, vehicles, etc.
- Blocks: Vehicle module features requiring images (photos, inspection reports, maintenance documentation)
- Solution: Extract media handling to polymorphic trait; implement on Gallery, then extend to Invoice/Company/Vehicle models

**Audit Logging for Sensitive Operations:**
- Problem: No audit trail for invoice modifications, company updates, or public gallery access
- Blocks: Cannot track who modified an invoice, when gallery was shared, or accessed by whom
- Solution: Implement activity logging for invoice state changes; log gallery access; audit user capability changes

**Export/Import for Multi-Year Data:**
- Problem: No bulk export for invoices; import mechanism exists only for legacy data
- Blocks: Tax reporting, data portability, disaster recovery scenarios
- Solution: Create export-to-CSV/JSON jobs; implement repeatable import patterns; test with realistic data volumes

## Test Coverage Gaps

**Filament Form/Table UI Interactions:**
- What's not tested: Complex form interactions (live field updates, dynamic field visibility, cascading selects) are not tested in isolation
- Files: `app/Filament/Invoices/Resources/Invoices/Schemas/InvoiceForm.php`, `app/Filament/Invoices/Resources/Invoices/Tables/InvoicesTable.php`
- Risk: Form refactoring could break conditional logic silently; table actions may fail on edge cases
- Priority: High (large files, high usage)

**PDF Generation Edge Cases:**
- What's not tested: PDF footer generation, locale switching, rich text escaping in items description
- Files: `app/Services/Invoices/InvoicePdfService.php`
- Risk: PDF templates could render malformed HTML; locale switching could expose XSS
- Priority: High (security-relevant, customer-facing output)

**File Upload and Storage Errors:**
- What's not tested: S3 upload failures, missing private disk, permission errors on file access
- Files: `app/Services/Invoices/InvoiceEmailService.php`, `app/Models/Invoices/Company.php`
- Risk: File operations fail silently or with unhelpful errors; users don't know why attachments weren't stored
- Priority: Medium (currently hidden by error_reporting: false)

**Gallery Public Endpoint Security:**
- What's not tested: Rate limiting, CSRF protection, access logging, IP-based restrictions
- Files: `app/Http/Controllers/Toolkit/GalleryShareController.php`
- Risk: Endpoint could be enumerated or abused without detection
- Priority: Medium (public endpoint)

**Exchange Rate Service Failures:**
- What's not tested: API timeouts, invalid currency codes, missing exchange rate data
- Files: `app/Services/Invoices/ExchangeRateService.php`
- Risk: Invoice generation could fail if exchange rate is unavailable; no fallback mechanism
- Priority: Low (command-based, not user-blocking)

**Vehicle Module Patterns (Preemptive):**
- What won't be testable: Custom OBD integration, AI analysis features, polymorphic relationship patterns don't exist yet
- Files: Not yet created
- Risk: When vehicle module is built, testing patterns must be established upfront to prevent fragility
- Priority: Medium (upcoming feature)

## Deployment & Environment Issues

**No Environment Variable Validation:**
- Issue: `.env.example` shows optional variables but there's no runtime check that required vars are set (Gotenberg URL, mail config, etc.)
- Impact: Deployment could proceed with missing config; features fail at runtime with unclear errors
- Fix: Add bootstrap validation in `AppServiceProvider`; throw exception on missing required env vars

**Database Migration Safety:**
- Issue: No pre-migration validation; migrations modify columns without checking existing data compatibility
- Files: `database/migrations/`
- Impact: Could lose data if migration assumptions are violated; rollback may not restore original state
- Fix: Add data migration tests; validate migration reversibility; document migration assumptions

---

*Concerns audit: 2026-04-30*
