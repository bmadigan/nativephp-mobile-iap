<?php

namespace Native\Mobile\Iap;

use Illuminate\Support\Collection;
use Native\Mobile\Iap\Contracts\IapDriver;
use Native\Mobile\Iap\DTOs\Purchase;
use Native\Mobile\Iap\Drivers\FakeDriver;
use Native\Mobile\Iap\Drivers\NativeDriver;
use Native\Mobile\Iap\Drivers\NullDriver;
use Native\Mobile\Iap\Pending\PendingProducts;
use Native\Mobile\Iap\Pending\PendingPurchase;
use Native\Mobile\Iap\Pending\PendingRestore;

class Iap implements IapDriver
{
    protected static ?FakeDriver $fakeDriver = null;

    protected ?IapDriver $driver = null;

    public function canMakePayments(): bool
    {
        return $this->resolveDriver()->canMakePayments();
    }

    /**
     * @param  array<int, string>  $productIds
     */
    public function products(array $productIds): PendingProducts
    {
        return $this->resolveDriver()->products($productIds);
    }

    public function purchase(string $productId): PendingPurchase
    {
        return $this->resolveDriver()->purchase($productId);
    }

    public function complete(Purchase $purchase): bool
    {
        return $this->resolveDriver()->complete($purchase);
    }

    public function restore(): PendingRestore
    {
        return $this->resolveDriver()->restore();
    }

    /**
     * @return Collection<int, \Native\Mobile\Iap\DTOs\Entitlement>
     */
    public function entitlements(): Collection
    {
        return $this->resolveDriver()->entitlements();
    }

    public function hasEntitlement(string $productId): bool
    {
        return $this->resolveDriver()->hasEntitlement($productId);
    }

    /**
     * @param  array<string, array<string, mixed>>  $products
     */
    public function register(array $products): void
    {
        $this->resolveDriver()->register($products);
    }

    public function isAvailable(): bool
    {
        return $this->resolveDriver()->isAvailable();
    }

    public static function fake(): FakeDriver
    {
        static::$fakeDriver = new FakeDriver;

        return static::$fakeDriver;
    }

    public static function isFake(): bool
    {
        return static::$fakeDriver !== null;
    }

    public static function clearFake(): void
    {
        static::$fakeDriver = null;
    }

    public static function getFakeDriver(): ?FakeDriver
    {
        return static::$fakeDriver;
    }

    protected function resolveDriver(): IapDriver
    {
        if (static::$fakeDriver !== null) {
            return static::$fakeDriver;
        }

        if ($this->driver !== null) {
            return $this->driver;
        }

        if ($this->isNativeEnvironment()) {
            $this->driver = new NativeDriver;
        } else {
            $this->driver = new NullDriver;
        }

        return $this->driver;
    }

    protected function isNativeEnvironment(): bool
    {
        return function_exists('nativephp_call') && config('nativephp-internal.running', false);
    }

    public function setDriver(IapDriver $driver): void
    {
        $this->driver = $driver;
    }

    public function getDriver(): IapDriver
    {
        return $this->resolveDriver();
    }
}
