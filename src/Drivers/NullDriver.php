<?php

namespace Native\Mobile\Iap\Drivers;

use Illuminate\Support\Collection;
use Native\Mobile\Iap\Contracts\IapDriver;
use Native\Mobile\Iap\DTOs\Purchase;
use Native\Mobile\Iap\Pending\PendingProducts;
use Native\Mobile\Iap\Pending\PendingPurchase;
use Native\Mobile\Iap\Pending\PendingRestore;

class NullDriver implements IapDriver
{
    protected array $registeredProducts = [];

    public function canMakePayments(): bool
    {
        return false;
    }

    public function products(array $productIds): PendingProducts
    {
        return new PendingProducts($productIds, isNative: false);
    }

    public function purchase(string $productId): PendingPurchase
    {
        return new PendingPurchase($productId, isNative: false);
    }

    public function complete(Purchase $purchase): bool
    {
        return false;
    }

    public function restore(): PendingRestore
    {
        return new PendingRestore(isNative: false);
    }

    public function entitlements(): Collection
    {
        return collect();
    }

    public function hasEntitlement(string $productId): bool
    {
        return false;
    }

    public function register(array $products): void
    {
        $this->registeredProducts = array_merge($this->registeredProducts, $products);
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function getRegisteredProducts(): array
    {
        return $this->registeredProducts;
    }
}
