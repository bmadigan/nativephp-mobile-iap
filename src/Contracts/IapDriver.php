<?php

namespace Native\Mobile\Iap\Contracts;

use Illuminate\Support\Collection;
use Native\Mobile\Iap\Pending\PendingProducts;
use Native\Mobile\Iap\Pending\PendingPurchase;
use Native\Mobile\Iap\Pending\PendingRestore;

interface IapDriver
{
    public function canMakePayments(): bool;

    /**
     * @param  array<int, string>  $productIds
     */
    public function products(array $productIds): PendingProducts;

    public function purchase(string $productId): PendingPurchase;

    public function restore(): PendingRestore;

    /**
     * @return Collection<int, \Native\Mobile\Iap\DTOs\Entitlement>
     */
    public function entitlements(): Collection;

    public function hasEntitlement(string $productId): bool;

    /**
     * @param  array<string, array<string, mixed>>  $products
     */
    public function register(array $products): void;

    public function isAvailable(): bool;
}
