# NativePHP Mobile IAP Plugin

## Project Identity
- **Package:** `bmadigan/nativephp-mobile-iap`
- **Type:** `nativephp-plugin`
- **Namespace:** `Native\Mobile\Iap`
- **PHP:** ^8.2 | Laravel 11/12

## Architecture
Driver pattern: `IapDriver` contract → `NativeDriver` (real device), `NullDriver` (non-native fallback), `FakeDriver` (testing).
Manager (`Iap.php`) resolves driver: fake > cached > native/null.
Fluent builders: `PendingProducts`, `PendingPurchase`, `PendingRestore`.

## Commands
```bash
composer install          # Install dependencies
composer validate         # Validate composer.json
vendor/bin/pest           # Run test suite
```

## File Organization
- `src/` — PHP source (Contracts, Drivers, DTOs, Enums, Events, Facades, Pending)
- `config/iap.php` — Product configuration
- `resources/ios/` — Swift (StoreKit 2)
- `resources/android/` — Kotlin (Google Play Billing 7.x)
- `resources/js/` — JavaScript bridge
- `tests/` — Pest tests (Unit + Feature)

## Key Conventions
- DTOs are `readonly` classes with `fromArray()` supporting both camelCase and snake_case
- Events use `Dispatchable` + `SerializesModels`
- FakeDriver uses anonymous classes extending Pending builders
- All native calls go through `nativephp_call()` bridge function
- Facade static methods (`fake()`, `isFake()`, `clearFake()`) bypass facade accessor

## Gotchas
- `Iap::$fakeDriver` is static — must call `Iap::clearFake()` in test teardown
- Pending builders auto-trigger in `__destruct()` if native and not started
- FakeDriver anonymous classes override `__destruct()` to prevent auto-trigger
