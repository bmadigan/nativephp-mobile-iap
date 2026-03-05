<?php

namespace Native\Mobile\Iap\Drivers;

use Illuminate\Support\Collection;
use Native\Mobile\Iap\Contracts\IapDriver;
use Native\Mobile\Iap\DTOs\Entitlement;
use Native\Mobile\Iap\Pending\PendingProducts;
use Native\Mobile\Iap\Pending\PendingPurchase;
use Native\Mobile\Iap\Pending\PendingRestore;

class NativeDriver implements IapDriver
{
    protected array $registeredProducts = [];

    public function canMakePayments(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $result = nativephp_call('Iap.CanMakePayments');

        if ($result) {
            $decoded = json_decode($result, true);

            return $decoded['canMakePayments'] ?? false;
        }

        return false;
    }

    public function products(array $productIds): PendingProducts
    {
        return new PendingProducts($productIds, isNative: true);
    }

    public function purchase(string $productId): PendingPurchase
    {
        return new PendingPurchase($productId, isNative: true);
    }

    public function restore(): PendingRestore
    {
        return new PendingRestore(isNative: true);
    }

    public function entitlements(): Collection
    {
        if (! function_exists('nativephp_call')) {
            return collect();
        }

        $result = nativephp_call('Iap.GetEntitlements');

        if ($result) {
            $decoded = json_decode($result, true);
            $entitlements = $decoded['entitlements'] ?? [];

            return collect($entitlements)->map(fn (array $data) => Entitlement::fromArray($data));
        }

        return collect();
    }

    public function hasEntitlement(string $productId): bool
    {
        return $this->entitlements()->contains(fn (Entitlement $e) => $e->productId === $productId && $e->isActive);
    }

    public function register(array $products): void
    {
        $this->registeredProducts = array_merge($this->registeredProducts, $products);

        if (function_exists('nativephp_call')) {
            nativephp_call('Iap.RegisterProducts', json_encode(['products' => $this->registeredProducts]));
        }
    }

    public function isAvailable(): bool
    {
        return function_exists('nativephp_call');
    }

    public function getRegisteredProducts(): array
    {
        return $this->registeredProducts;
    }
}
