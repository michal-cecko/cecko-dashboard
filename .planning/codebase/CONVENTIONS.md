# Coding Conventions

**Analysis Date:** 2025-04-30

## Naming Patterns

**Files:**
- PHP classes: PascalCase (e.g., `InvoiceResource.php`, `InvoiceNumberService.php`, `InvoiceStatusEnum.php`)
- Directories: PascalCase for grouped domains (e.g., `app/Filament/Invoices/Resources/`, `app/Services/Invoices/`, `database/factories/`)

**Functions/Methods:**
- camelCase for all public and private methods (e.g., `generateNextNumber()`, `formatNumber()`, `translateStringColorToHex()`, `hasCapability()`)
- Descriptive names preferred over abbreviations (e.g., `isEditable()`, `paidAmount()`, `remainingAmount()`, `paymentPercentage()`)
- Boolean methods prefix with `is`, `has`, `can` (e.g., `isEditable()`, `hasCapability()`, `canAccessPanel()`)

**Variables:**
- camelCase for all variables and properties (e.g., `$lockKey`, `$currentYear`, `$sequenceNumber`, `$padding`)
- Private properties with visibility declared (e.g., `private InvoiceNumberService $service`)

**Types/Classes:**
- Enum cases: UPPER_CASE (e.g., `NEW`, `SENT`, `PAID`, `CANCEL`, `REVERSE_CHARGE`)
- Enum class names: TitleCase (e.g., `InvoiceStatusEnum`, `PaymentMethodEnum`, `VatTypeEnum`)
- Trait names: descriptive (e.g., `BelongsToActiveCompany`, `EnumHelper`, `HasTranslations`)

## Code Style

**Formatting:**
- Laravel Pint (v1.13) configured via default Laravel standards
- No custom `.pint.json` — uses Laravel defaults
- Code must follow strict formatting via Pint before finalizing

**Linting:**
- Laravel Pint is the code formatter; run `vendor/bin/sail bin pint --dirty` on modified files
- No separate linting configuration in project

**Control Structures:**
- Always use curly braces, even for single statements (required by Laravel Boost guidelines)

Example:
```php
if ($sequence->reset_yearly && $sequence->last_reset_year !== $currentYear) {
    $sequence->next_number = 1;
    $sequence->last_reset_year = $currentYear;
}
```

## Import Organization

**Order:**
1. Standard library imports (e.g., `use Carbon\Carbon`)
2. Laravel framework imports (e.g., `use Illuminate\Database\Eloquent\Model`)
3. Filament imports (e.g., `use Filament\Forms\Components\TextInput`)
4. Application-specific imports (e.g., `use App\Models\Invoices\Invoice`)
5. Type declarations (e.g., `use BackedEnum, UnitEnum`)

**Path Aliases:**
No custom path aliases configured. All imports use full namespace paths.

## Error Handling

**Patterns:**
- Leverage Laravel's exception handling via service container
- No try-catch blocks in most code; rely on Laravel's error handler middleware
- Return appropriate HTTP status codes via Laravel's response system
- Services throw exceptions; controllers/Filament resources handle them through Laravel's exception handler

Example from `InvoiceNumberService`:
```php
return Cache::lock($lockKey, 10)->block(5, function () use ($sequence) {
    // Service logic; exceptions propagate to handler
});
```

## Logging

**Framework:** `Illuminate\Support\Facades\Log` (not explicitly shown, but standard Laravel logging pattern)

**Patterns:**
- Logging not heavily demonstrated in sampled code; logs are typically structured through Laravel's logging facade
- Error logging handled by Sentry integration (`sentry/sentry-laravel` v4.0)

## Comments

**When to Comment:**
- Comments are minimal; code is self-documenting via descriptive naming
- PHPDoc blocks used for complex functions and return type hints
- Inline comments avoided unless logic is exceptionally complex

**PHPDoc/TSDoc:**
- Class-level docblocks for factories: `/** @extends Factory<Model> */`
- Method return type declarations (see Type Declarations section below)
- Parameter documentation in complex Filament closures

Example:
```php
/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;
    
    public function definition(): array
    {
        // ...
    }
}
```

## Type Declarations

**Return Types (Mandatory):**
- All functions and methods must have explicit return type declarations
- Used on factory methods, services, controllers, and helpers

Examples:
```php
public function generateNextNumber(InvoiceNumberSequence $sequence): string
public function formatNumber(string $format, int $sequenceNumber, int $padding): string
public function definition(): array
public static function configure(Schema $schema): Schema
```

**Parameter Type Hints (Mandatory):**
- All parameters must have type hints (no mixed/implicit types)
- Use union types when appropriate

Examples:
```php
protected function isAccessible(User $user, ?string $path = null): bool
public function canAccessPanel(Panel $panel): bool
public function hasCapability(UserCapabilityEnum $capability): bool
```

**Constructor Property Promotion:**
- PHP 8.3 constructor property promotion preferred; avoid empty constructors
- Use visibility modifiers in constructor parameters

Examples of proper pattern:
```php
public function __construct(public GitHub $github) { }
public function __construct(public SomeService $service, private AnotherService $another) { }
```

NOT this (incorrect):
```php
public function __construct() { }  // Empty — avoid this
```

**Array Type Hints with PHPDoc:**
- Use PHPDoc for array shapes when helpful for IDE clarity

Example from factory:
```php
/**
 * @return array<string, mixed>
 */
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
    ];
}
```

**Casting:**
- Use `casts()` method (not `$casts` property) as per Laravel 12 modern pattern
- All models use `protected function casts(): array` instead of static property

Example from `User` model (`app/Models/Common/User.php`):
```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'capabilities' => UserCapabilitiesCast::class,
    ];
}
```

Example from `Invoice` model:
```php
protected function casts(): array
{
    return [
        'status' => InvoiceStatusEnum::class,
        'payment_method' => PaymentMethodEnum::class,
        'exchange_rate' => 'decimal:6',
        'issue_date' => 'date',
        'text_before_items' => 'array',
        'buyer_snapshot' => 'array',
        'sent_at' => 'datetime',
    ];
}
```

## Function Design

**Size:** Methods should be focused and single-responsibility; most are 5–30 lines

**Parameters:** Use type hints; default to required parameters unless optional makes sense

**Return Values:**
- Always return a single, clearly-typed value
- Use return type declarations for all functions

## Enum Pattern with EnumHelper Trait

**All enums use the EnumHelper trait** for translation support:

Example (`app/Enums/Invoices/InvoiceStatusEnum.php`):
```php
<?php

namespace App\Enums\Invoices;

use App\Traits\Common\EnumHelper;

enum InvoiceStatusEnum: string
{
    use EnumHelper;

    case NEW = 'new';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case AFTER_DUE = 'after_due';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}
```

**Enum Values:** All backed enums use lowercase string values (e.g., `'new'`, `'sent'`, `'paid'`)

**Translations:**
- All enum translations stored in `lang/sk/enums.php` with Slovak labels
- Accessed via `$enum->translation()` method from EnumHelper trait
- Format in `lang/sk/enums.php`:

```php
InvoiceStatusEnum::class => [
    InvoiceStatusEnum::NEW->value => 'Nová',
    InvoiceStatusEnum::SENT->value => 'Odoslaná',
    InvoiceStatusEnum::PAID->value => 'Zaplatená',
],
```

## Module Design

**Exports:** Static factory methods for configuration (see Filament Resources below)

**Barrel Files:** Not used; full namespaces imported

## Filament Resource Configuration Pattern

**Resources follow a strict architectural pattern:**

**File structure:**
```
app/Filament/{Domain}/Resources/{ModelName}/
├── {ModelName}Resource.php           # Main resource class
├── Schemas/
│   └── {ModelName}Form.php          # Static configure() method for form
├── Tables/
│   └── {ModelName}sTable.php        # Static configure() method for table
├── Pages/
│   ├── Create{ModelName}.php
│   ├── Edit{ModelName}.php
│   ├── List{ModelName}s.php
│   └── View{ModelName}.php
└── RelationManagers/
    └── {RelationName}RelationManager.php
```

**Resource Class Pattern** (`app/Filament/Invoices/Resources/Invoices/InvoiceResource.php`):
- Static properties for metadata: `$model`, `$navigationIcon`, `$navigationGroup`, `$label`, `$pluralLabel`
- Icons use `Heroicon` enum: `Heroicon::OutlinedDocumentText` (NOT string literals)
- `form()` and `table()` methods call static `configure()` on separate classes
- `getRelations()` and `getPages()` return arrays of classes

```php
public static function form(Schema $schema): Schema
{
    return InvoiceForm::configure($schema);
}

public static function table(Table $table): Table
{
    return InvoicesTable::configure($table);
}
```

**Form/Table Configuration Classes:**
- Static `configure()` method accepts the Schema/Table and returns configured schema/table
- All form fields/table columns defined inline
- Uses closures for dynamic behavior via `Get` and `Set` utilities

Example pattern from `InvoiceForm::configure()`:
```php
public static function configure(Schema $schema): Schema
{
    return $schema
        ->components([
            Section::make('Základné údaje')
                ->schema([
                    TextInput::make('invoice_number')
                        ->label('Číslo faktúry')
                        ->required(),
                    // ... more fields
                ]),
        ]);
}
```

## Model Labels in Slovak

**All Filament Resource labels are in Slovak:**

```php
protected static ?string $label = 'Faktúra';
protected static ?string $pluralLabel = 'Faktúry';
protected static ?string $navigationGroup = 'Faktúry';
```

Form/Table component labels also in Slovak:
```php
TextInput::make('invoice_number')->label('Číslo faktúry')
Select::make('customer_id')->label('Odberateľ')
```

Language strings stored in `lang/sk/` files.

---

*Conventions analysis: 2025-04-30*
