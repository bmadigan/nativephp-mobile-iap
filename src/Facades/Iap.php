<?php

namespace Native\Mobile\Iap\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Native\Mobile\Iap\Contracts\IapDriver;
use Native\Mobile\Iap\DTOs\Purchase;
use Native\Mobile\Iap\Drivers\FakeDriver;
use Native\Mobile\Iap\Pending\PendingProducts;
use Native\Mobile\Iap\Pending\PendingPurchase;
use Native\Mobile\Iap\Pending\PendingRestore;

/**
 * @method static bool canMakePayments()
 * @method static PendingProducts products(array $productIds)
 * @method static PendingPurchase purchase(string $productId)
 * @method static bool complete(Purchase $purchase)
 * @method static PendingRestore restore()
 * @method static Collection entitlements()
 * @method static bool hasEntitlement(string $productId)
 * @method static void register(array $products)
 * @method static bool isAvailable()
 * @method static void setDriver(IapDriver $driver)
 * @method static IapDriver getDriver()
 * @method static FakeDriver fake()
 * @method static bool isFake()
 * @method static void clearFake()
 * @method static FakeDriver|null getFakeDriver()
 *
 * @see \Native\Mobile\Iap\Iap
 */
class Iap extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Native\Mobile\Iap\Iap::class;
    }

    public static function fake(): FakeDriver
    {
        return \Native\Mobile\Iap\Iap::fake();
    }

    public static function isFake(): bool
    {
        return \Native\Mobile\Iap\Iap::isFake();
    }

    public static function clearFake(): void
    {
        \Native\Mobile\Iap\Iap::clearFake();
    }

    public static function getFakeDriver(): ?FakeDriver
    {
        return \Native\Mobile\Iap\Iap::getFakeDriver();
    }
}
