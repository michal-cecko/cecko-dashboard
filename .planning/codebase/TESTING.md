# Testing Patterns

**Analysis Date:** 2025-04-30

## Test Framework

**Runner:**
- PHPUnit 12.0 (v12.0 in composer.json shows latest PHPUnit)
- Config: `phpunit.xml`

**Assertion Library:**
- Native PHPUnit assertions (`$this->assertEquals()`, `$this->assertTrue()`, `$this->assertFalse()`, etc.)
- No external assertion library

**Run Commands:**
```bash
vendor/bin/sail artisan test --compact              # Run all tests
vendor/bin/sail artisan test tests/Feature/*.php    # Run feature tests
vendor/bin/sail artisan test --filter=testName      # Run specific test by name
vendor/bin/sail artisan test tests/Unit/*.php       # Run unit tests
```

## Test File Organization

**Location:**
- Feature tests: `tests/Feature/` (database-dependent, integration tests)
- Unit tests: `tests/Unit/` (isolated, no database)
- TestCase base: `tests/TestCase.php`

**Naming:**
- Test files match the feature/component: `InvoiceModelTest.php`, `InvoicePolicyTest.php`, `SongApiTest.php`
- Test methods: `test_<description>` using snake_case (e.g., `test_is_editable_returns_true_for_new_invoice()`)

**Structure:**
```
tests/
├── Feature/
│   ├── InvoiceModelTest.php
│   ├── InvoicePolicyTest.php
│   ├── GalleryPolicyTest.php
│   ├── SongApiTest.php
│   └── ... (more feature tests)
├── Unit/
│   ├── InvoiceNumberServiceTest.php
│   ├── ColorServiceTest.php
│   └── ... (more unit tests)
└── TestCase.php
```

## Test Structure

**Suite Organization:**
PHPUnit organized by test suite in `phpunit.xml`:
```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
</testsuites>
```

**Patterns:**

**Unit Test Structure** (from `tests/Unit/InvoiceNumberServiceTest.php`):
```php
<?php

namespace Tests\Unit;

use App\Services\Invoices\InvoiceNumberService;
use PHPUnit\Framework\TestCase;

class InvoiceNumberServiceTest extends TestCase
{
    private InvoiceNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceNumberService;
    }

    public function test_format_number_with_year_and_sequence(): void
    {
        $result = $this->service->formatNumber('{YEAR}-{SEQ}', 1, 4);

        $this->assertEquals(now()->format('Y').'-0001', $result);
    }
}
```

**Feature Test Structure** (from `tests/Feature/InvoiceModelTest.php`):
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([...]);
        $this->company = Company::factory()->create([...]);
        
        $this->user->update(['active_company_id' => $this->company->id]);
    }

    public function test_is_editable_returns_true_for_new_invoice(): void
    {
        $invoice = Invoice::factory()->draft()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertTrue($invoice->isEditable());
    }
}
```

**Setup/Teardown:**
- `setUp()` method called before each test (called via `protected function setUp(): void`)
- `RefreshDatabase` trait used in feature tests — automatically resets database between tests
- No explicit tearDown() used; `RefreshDatabase` handles cleanup

**Assertion Patterns:**
- Simple assertion: `$this->assertEquals(expected, actual, 'Message')`
- Boolean: `$this->assertTrue()`, `$this->assertFalse()`
- Null checks: `$this->assertNull()`, `$this->assertNotNull()`
- JSON responses: `$response->assertJsonFragment(['key' => 'value'])`
- HTTP assertions: `$response->assertOk()`, `$response->assertRedirect()`
- HTTP status codes: `$response->assertStatus(200)`

## Mocking

**Framework:** Mockery (v1.6 in composer.json, but most tests use factories instead of mocks)

**Patterns:**
Most tests use **factories** instead of mocking. Direct model instantiation via factories is preferred over mocks.

Example (actual pattern from tests):
```php
$invoice = Invoice::factory()->draft()->create([
    'company_id' => $this->company->id,
    'customer_id' => $this->customer->id,
]);
```

**What to Mock:**
- External HTTP calls (not shown in sampled tests, but would use Mockery for API clients)
- Time-dependent code (use Carbon freezing or injection, not mocks)

**What NOT to Mock:**
- Database models — use factories instead
- Eloquent relationships — use factories with relationships
- Service methods that don't have external dependencies

## Fixtures and Factories

**Test Data:**
Factories define test data. Each factory extends `Factory<Model>` with PHPDoc type hint.

**Factory Example** (`database/factories/InvoiceFactory.php`):
```php
<?php

namespace Database\Factories;

use App\Enums\Invoices\InvoiceStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'invoice_number' => fake()->unique()->numerify('####-####'),
            'status' => fake()->randomElement(InvoiceStatusEnum::cases()),
            'currency' => 'EUR',
            'issue_date' => $issueDate,
            'due_date' => (clone $issueDate)->modify('+14 days'),
            'total' => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatusEnum::PAID,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatusEnum::NEW,
        ]);
    }
}
```

**Factory States:**
- Named states as methods returning `static` via `$this->state()`
- States are chainable: `Invoice::factory()->draft()->create()`
- Examples: `.paid()`, `.draft()`, `.vatPayer()`

**Faker Usage Convention:**
- Uses `fake()` helper function (Laravel 11+ preferred style)
- Common Faker methods: `fake()->name()`, `fake()->email()`, `fake()->company()`, `fake()->numerify()`, `fake()->dateTimeBetween()`
- Unique values: `fake()->unique()->safeEmail()`
- Random selection: `fake()->randomElement(EnumClass::cases())`

Location: `database/factories/` — automatically discovered via PSR-4 autoload

## Coverage

**Requirements:** No explicit coverage requirements configured in `phpunit.xml`

**View Coverage:**
```bash
vendor/bin/sail artisan test --coverage               # Display coverage report
vendor/bin/sail artisan test --coverage-html=./html  # Generate HTML report
```

## Test Types

**Unit Tests:**
- Scope: Single service/utility class in isolation
- Location: `tests/Unit/`
- Example: `InvoiceNumberServiceTest`, `ColorServiceTest`
- No database, no Laravel container
- Extend `PHPUnit\Framework\TestCase` directly

```php
use PHPUnit\Framework\TestCase;

class InvoiceNumberServiceTest extends TestCase
{
    private InvoiceNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceNumberService;
    }
}
```

**Feature Tests:**
- Scope: Integration tests involving database, models, relationships, authorization
- Location: `tests/Feature/`
- Examples: `InvoiceModelTest`, `InvoicePolicyTest`, `GalleryPolicyTest`, `SongApiTest`
- Use `RefreshDatabase` trait for database isolation
- Extend `Tests\TestCase` (custom class in `tests/TestCase.php`)

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePolicyTest extends TestCase
{
    use RefreshDatabase;
    
    // Tests here
}
```

**E2E Tests:**
- Not currently used in codebase (no browser-based tests detected)
- Would use Laravel Dusk if implemented in future

## Authentication in Tests

**Pattern:**
Use `$this->actingAs($user)` to set authenticated user for test:

Example from `InvoiceModelTest.php`:
```php
public function test_belongs_to_active_company_scopes_queries(): void
{
    $this->actingAs($this->user);

    $otherUser = User::factory()->create();
    $otherCompany = Company::factory()->create(['user_id' => $otherUser->id]);

    // Test queries with $this->user authenticated
    $invoices = Invoice::all();
    $this->assertCount(1, $invoices);
}
```

**Helper Method Pattern:**
Common helper for tests that need users with specific capabilities:

From `InvoicePolicyTest.php`:
```php
private function createUserWithCapabilities(array $capabilities): User
{
    return User::factory()->create([
        'capabilities' => $capabilities,
    ]);
}
```

## Common Assertion Patterns

**Model Relationships:**
```php
$invoice = Invoice::factory()->draft()->create([...]);
$this->assertTrue($invoice->isEditable());
$this->assertFalse($invoice->isPaid());
```

**Database State:**
```php
$this->assertEquals(150.0, $invoice->paidAmount());
$this->assertEquals(125.0, $invoice->remainingAmount());
$this->assertEquals(50, $invoice->paymentPercentage());
```

**JSON API Responses** (from `SongApiTest.php`):
```php
$response = $this->getJson('/api/songs');

$response->assertOk();
$response->assertJsonCount(3);
$response->assertJsonFragment(['name' => 'Test Artist']);
```

**Authorization Policies** (from `InvoicePolicyTest.php`):
```php
$this->assertTrue($user->can('create', Invoice::class));
$this->assertFalse($user->can('delete', $invoice));
$this->assertTrue($user->can('viewAny', Invoice::class));
```

**Filament Table Testing Patterns** (pattern for future Filament tests):
```php
livewire(ListInvoices::class)
    ->assertCanSeeTableRecords($invoices)
    ->searchTable($invoice->invoice_number)
    ->assertCanSeeTableRecords($invoices->take(1));
```

**Filament Form Validation** (pattern for future Filament tests):
```php
livewire(CreateInvoice::class)
    ->fillForm([
        'invoice_number' => null,
        'customer_id' => null,
    ])
    ->call('create')
    ->assertHasFormErrors([
        'invoice_number' => 'required',
        'customer_id' => 'required',
    ]);
```

## Test Environment Configuration

**Environment Variables** (from `phpunit.xml`):
```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="pgsql"/>
<env name="MAIL_MAILER" value="array"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="BCRYPT_ROUNDS" value="4"/>
```

All tests run against PostgreSQL test database with in-memory cache, array mail driver, and sync queue.

## Running Tests via Sail

All tests must be executed through Laravel Sail Docker containers:

```bash
vendor/bin/sail artisan test --compact                    # All tests
vendor/bin/sail artisan test tests/Feature/InvoiceModelTest.php  # Single file
vendor/bin/sail artisan test --filter=testMethodName     # Single test
vendor/bin/sail artisan test tests/Unit --parallel       # Unit tests in parallel
```

---

*Testing analysis: 2025-04-30*
