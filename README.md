# NativePHP Mobile IAP

In-App Purchase plugin for [NativePHP Mobile](https://nativephp.com) — StoreKit 2 (iOS) & Google Play Billing 7.x (Android).

## Installation

```bash
composer require bmadigan/nativephp-mobile-iap
```

The service provider and facade are auto-discovered by Laravel.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=nativephp-iap-config
```

Define your products in `config/iap.php`:

```php
'products' => [
    'app_lifetime' => [
        'type' => 'non_consumable',
        'name' => 'Lifetime Access',
        'description' => 'Unlock all features forever',
    ],
    'app_monthly' => [
        'type' => 'subscription',
        'name' => 'Monthly Subscription',
        'description' => 'Access all premium features',
    ],
],
```

## Usage

### Check Payment Availability

```php
use Native\Mobile\Iap\Facades\Iap;

if (Iap::canMakePayments()) {
    // Device supports in-app purchases
}
```

### Load Products

```php
Iap::products(['app_lifetime', 'app_monthly'])->load();
```

Listen for the result:

```php
use Native\Mobile\Iap\Events\ProductsLoaded;

Event::listen(ProductsLoaded::class, function ($event) {
    $products = $event->products;    // Collection of Product DTOs
    $invalid = $event->invalidIds;   // Product IDs not found in store
});
```

### Purchase a Product

```php
Iap::purchase('app_lifetime')->start();
```

```php
use Native\Mobile\Iap\Events\PurchaseCompleted;
use Native\Mobile\Iap\Events\PurchaseFailed;
use Native\Mobile\Iap\Events\PurchaseCancelled;

Event::listen(PurchaseCompleted::class, function ($event) {
    $event->productId;
    $event->purchase;        // Purchase DTO
    $event->signedPayload;   // JWS token for server verification
    $event->isSandbox;
});
```

### Restore Purchases

```php
Iap::restore()->start();
```

### Check Entitlements

```php
if (Iap::hasEntitlement('app_lifetime')) {
    // User owns this product
}

$entitlements = Iap::entitlements(); // Collection of Entitlement DTOs
```

### JavaScript Bridge

```javascript
import { canMakePayments, getProducts, purchase, restore, Events } from '@nativephp/iap';

const result = await purchase('app_lifetime');
```

## Testing

Use the built-in fake driver for testing without hitting real stores:

```php
use Native\Mobile\Iap\Facades\Iap;

Iap::fake();

// Configure mock data
Iap::getFakeDriver()
    ->addMockProduct('lifetime', '$29.99')
    ->addMockEntitlement('lifetime', true)
    ->shouldSucceed(); // or ->shouldFail('code', 'message') or ->shouldCancel()

// Run your code
Iap::purchase('lifetime')->start();

// Assert
Iap::getFakeDriver()->assertPurchased('lifetime');
```

Run the test suite:

```bash
vendor/bin/pest
```

## Supported Product Types

| Type           | Enum             | Description                       |
| -------------- | ---------------- | --------------------------------- |
| Consumable     | `consumable`     | Can be purchased multiple times   |
| Non-Consumable | `non_consumable` | One-time permanent purchase       |
| Subscription   | `subscription`   | Recurring auto-renewable          |

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- NativePHP Mobile v3+
- iOS 15+ (StoreKit 2)
- Android with Google Play Billing 7.1.1

## License

Proprietary. See [LICENSE](LICENSE) for details.
