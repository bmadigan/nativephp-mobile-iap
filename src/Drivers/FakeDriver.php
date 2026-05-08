<?php

namespace Native\Mobile\Iap\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Native\Mobile\Iap\Contracts\IapDriver;
use Native\Mobile\Iap\DTOs\Entitlement;
use Native\Mobile\Iap\DTOs\Product;
use Native\Mobile\Iap\DTOs\Purchase;
use Native\Mobile\Iap\Enums\ProductType;
use Native\Mobile\Iap\Enums\PurchaseState;
use Native\Mobile\Iap\Events\ProductsLoaded;
use Native\Mobile\Iap\Events\PurchaseCancelled;
use Native\Mobile\Iap\Events\PurchaseCompleted;
use Native\Mobile\Iap\Events\PurchaseFailed;
use Native\Mobile\Iap\Events\RestoreCompleted;
use Native\Mobile\Iap\Pending\PendingProducts;
use Native\Mobile\Iap\Pending\PendingPurchase;
use Native\Mobile\Iap\Pending\PendingRestore;
use PHPUnit\Framework\Assert;

class FakeDriver implements IapDriver
{
    protected bool $canMakePayments = true;

    protected string $purchaseOutcome = 'success';

    protected string $failCode = 'UNKNOWN';

    protected string $failMessage = 'Unknown error';

    /** @var array<string, Product> */
    protected array $mockProducts = [];

    /** @var array<string, Entitlement> */
    protected array $mockEntitlements = [];

    /** @var array<string, array<string, mixed>> */
    protected array $registeredProducts = [];

    /** @var array<int, string> */
    protected array $purchasedProducts = [];

    /** @var array<int, string> */
    protected array $completedProducts = [];

    /** @var array<int, array<int, string>> */
    protected array $loadedProducts = [];

    protected int $restoreCount = 0;

    public function canMakePayments(): bool
    {
        return $this->canMakePayments;
    }

    public function setCanMakePayments(bool $canMake): self
    {
        $this->canMakePayments = $canMake;

        return $this;
    }

    public function shouldSucceed(): self
    {
        $this->purchaseOutcome = 'success';

        return $this;
    }

    public function shouldFail(string $code = 'UNKNOWN', string $message = 'Unknown error'): self
    {
        $this->purchaseOutcome = 'fail';
        $this->failCode = $code;
        $this->failMessage = $message;

        return $this;
    }

    public function shouldCancel(): self
    {
        $this->purchaseOutcome = 'cancel';

        return $this;
    }

    public function addMockProduct(string $id, string $price = '$0.00', ?ProductType $type = null): self
    {
        $productType = $type ?? ProductType::NonConsumable;

        $this->mockProducts[$id] = new Product(
            id: $id,
            title: $id,
            description: '',
            price: $price,
            priceAmount: (float) preg_replace('/[^0-9.]/', '', $price),
            currency: 'USD',
            type: $productType,
            subscriptionPeriod: $productType->isSubscription() ? 'P1M' : null,
        );

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    public function mockProducts(array $products): self
    {
        foreach ($products as $product) {
            $p = Product::fromArray($product);
            $this->mockProducts[$p->id] = $p;
        }

        return $this;
    }

    public function addMockEntitlement(string $productId, bool $isActive, ?\Carbon\Carbon $expiresAt = null): self
    {
        $transactionId = (string) Str::uuid();

        $this->mockEntitlements[$productId] = new Entitlement(
            productId: $productId,
            isActive: $isActive,
            transactionId: $transactionId,
            originalTransactionId: $transactionId,
            expiresAt: $expiresAt,
        );

        return $this;
    }

    public function products(array $productIds): PendingProducts
    {
        $driver = $this;

        return new class($productIds, $driver) extends PendingProducts
        {
            public function __construct(
                array $productIds,
                protected FakeDriver $driver
            ) {
                parent::__construct($productIds, isNative: false);
            }

            public function load(): bool
            {
                if ($this->started) {
                    return false;
                }

                $this->started = true;

                $this->driver->recordProductsLoaded($this->productIds);

                $matched = collect();
                $invalidIds = [];

                foreach ($this->productIds as $id) {
                    $product = $this->driver->getMockProduct($id);
                    if ($product !== null) {
                        $matched->push($product);
                    } else {
                        $invalidIds[] = $id;
                    }
                }

                ProductsLoaded::dispatch($matched, $invalidIds, $this->getId());

                return true;
            }

            public function __destruct()
            {
                // Override to prevent parent auto-load
            }
        };
    }

    public function purchase(string $productId): PendingPurchase
    {
        $driver = $this;

        return new class($productId, $driver) extends PendingPurchase
        {
            public function __construct(
                string $productId,
                protected FakeDriver $driver
            ) {
                parent::__construct($productId, isNative: false);
            }

            public function start(): bool
            {
                if ($this->started) {
                    return false;
                }

                $this->started = true;

                $this->driver->executePurchase($this->productId, $this->getId());

                return true;
            }

            public function __destruct()
            {
                // Override to prevent parent auto-start
            }
        };
    }

    public function restore(): PendingRestore
    {
        $driver = $this;

        return new class($driver) extends PendingRestore
        {
            public function __construct(
                protected FakeDriver $driver
            ) {
                parent::__construct(isNative: false);
            }

            public function start(): bool
            {
                if ($this->started) {
                    return false;
                }

                $this->started = true;

                $this->driver->executeRestore($this->getId());

                return true;
            }

            public function __destruct()
            {
                // Override to prevent parent auto-start
            }
        };
    }

    public function complete(Purchase $purchase): bool
    {
        $this->completedProducts[] = $purchase->productId;

        return true;
    }

    public function entitlements(): Collection
    {
        return collect($this->mockEntitlements)->filter(fn (Entitlement $e) => $e->isActive)->values();
    }

    public function hasEntitlement(string $productId): bool
    {
        return isset($this->mockEntitlements[$productId]) && $this->mockEntitlements[$productId]->isActive;
    }

    public function register(array $products): void
    {
        $this->registeredProducts = array_merge($this->registeredProducts, $products);
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getRegisteredProducts(): array
    {
        return $this->registeredProducts;
    }

    // --- Internal methods used by anonymous classes ---

    /** @internal */
    public function executePurchase(string $productId, string $id): void
    {
        switch ($this->purchaseOutcome) {
            case 'success':
                $this->purchasedProducts[] = $productId;
                $transactionId = (string) Str::uuid();
                $purchase = new Purchase(
                    productId: $productId,
                    transactionId: $transactionId,
                    originalTransactionId: $transactionId,
                    state: PurchaseState::Completed,
                    purchaseDate: now(),
                    isSandbox: true,
                );
                PurchaseCompleted::dispatch($productId, $purchase, null, $id, true);
                break;

            case 'fail':
                PurchaseFailed::dispatch($productId, $this->failCode, $this->failMessage, $id);
                break;

            case 'cancel':
                PurchaseCancelled::dispatch($productId, $id);
                break;
        }
    }

    /** @internal */
    public function executeRestore(string $id): void
    {
        $this->restoreCount++;

        $purchases = collect($this->mockEntitlements)
            ->filter(fn (Entitlement $e) => $e->isActive)
            ->map(fn (Entitlement $e) => new Purchase(
                productId: $e->productId,
                transactionId: $e->transactionId,
                originalTransactionId: $e->originalTransactionId,
                state: PurchaseState::Completed,
                purchaseDate: now(),
                expiresAt: $e->expiresAt,
                isSandbox: true,
            ))
            ->values();

        RestoreCompleted::dispatch($purchases, $id);
    }

    /** @internal */
    public function recordProductsLoaded(array $productIds): void
    {
        $this->loadedProducts[] = $productIds;
    }

    /** @internal */
    public function getMockProduct(string $id): ?Product
    {
        return $this->mockProducts[$id] ?? null;
    }

    // --- Assertions ---

    public function assertPurchased(string $productId): self
    {
        Assert::assertContains(
            $productId,
            $this->purchasedProducts,
            "Expected product [{$productId}] to have been purchased."
        );

        return $this;
    }

    public function assertNothingPurchased(): self
    {
        Assert::assertEmpty(
            $this->purchasedProducts,
            'Expected no products to have been purchased, but '.count($this->purchasedProducts).' were.'
        );

        return $this;
    }

    public function assertCompleted(string $productId): self
    {
        Assert::assertContains(
            $productId,
            $this->completedProducts,
            "Expected product [{$productId}] to have been completed."
        );

        return $this;
    }

    public function assertRestored(): self
    {
        Assert::assertGreaterThan(
            0,
            $this->restoreCount,
            'Expected restore to have been called at least once.'
        );

        return $this;
    }

    public function assertProductsLoaded(array $productIds): self
    {
        $found = false;

        foreach ($this->loadedProducts as $loaded) {
            if ($loaded === $productIds) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue(
            $found,
            'Expected products ['.implode(', ', $productIds).'] to have been loaded.'
        );

        return $this;
    }
}
