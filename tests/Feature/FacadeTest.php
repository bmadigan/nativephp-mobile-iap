<?php

use Illuminate\Support\Facades\Event;
use Native\Mobile\Iap\Drivers\FakeDriver;
use Native\Mobile\Iap\Drivers\NullDriver;
use Native\Mobile\Iap\DTOs\Purchase;
use Native\Mobile\Iap\Enums\PurchaseState;
use Native\Mobile\Iap\Events\PurchaseCompleted;
use Native\Mobile\Iap\Facades\Iap;

describe('Iap Facade', function () {
    afterEach(function () {
        Iap::clearFake();
    });

    describe('driver resolution', function () {
        it('uses NullDriver in non-native environment', function () {
            expect(Iap::getDriver())->toBeInstanceOf(NullDriver::class);
        });

        it('reports as unavailable in non-native environment', function () {
            expect(Iap::isAvailable())->toBeFalse();
        });

        it('cannot make payments in non-native environment', function () {
            expect(Iap::canMakePayments())->toBeFalse();
        });
    });

    describe('fake mode', function () {
        it('can enable fake mode', function () {
            $fake = Iap::fake();
            expect($fake)->toBeInstanceOf(FakeDriver::class)
                ->and(Iap::isFake())->toBeTrue();
        });

        it('uses FakeDriver when in fake mode', function () {
            Iap::fake();
            expect(Iap::isAvailable())->toBeTrue()
                ->and(Iap::canMakePayments())->toBeTrue();
        });

        it('clears fake mode', function () {
            Iap::fake();
            expect(Iap::isFake())->toBeTrue();
            Iap::clearFake();
            expect(Iap::isFake())->toBeFalse();
        });

        it('returns the fake driver for configuration', function () {
            $fake = Iap::fake();
            expect(Iap::getFakeDriver())->toBe($fake);
        });
    });

    describe('facade methods with fake', function () {
        beforeEach(function () {
            Iap::fake();
            Event::fake();
        });

        it('can purchase via facade', function () {
            Iap::purchase('test_product')->start();
            Event::assertDispatched(PurchaseCompleted::class, function ($event) {
                return $event->productId === 'test_product';
            });
            Iap::getFakeDriver()->assertPurchased('test_product');
        });

        it('can complete purchase via facade', function () {
            $purchase = new Purchase('test_product', 'txn', 'orig', PurchaseState::Completed, now());
            expect(Iap::complete($purchase))->toBeTrue();
            Iap::getFakeDriver()->assertCompleted('test_product');
        });

        it('can configure and use entitlements', function () {
            Iap::fake()->addMockEntitlement('lifetime', true);
            expect(Iap::hasEntitlement('lifetime'))->toBeTrue()
                ->and(Iap::hasEntitlement('unknown'))->toBeFalse();
        });

        it('can register products via facade', function () {
            Iap::register(['product1' => ['type' => 'non_consumable', 'name' => 'Product 1']]);
            expect(Iap::getFakeDriver()->getRegisteredProducts())->toHaveKey('product1');
        });
    });

    describe('fluent builders via facade', function () {
        beforeEach(function () { Iap::fake(); });

        it('returns PendingProducts from products()', function () {
            $pending = Iap::products(['product1', 'product2']);
            expect($pending->getProductIds())->toBe(['product1', 'product2']);
        });

        it('returns PendingPurchase from purchase()', function () {
            $pending = Iap::purchase('test_product');
            expect($pending->getProductId())->toBe('test_product');
        });

        it('returns PendingRestore from restore()', function () {
            $pending = Iap::restore();
            expect($pending)->toBeInstanceOf(\Native\Mobile\Iap\Pending\PendingRestore::class);
        });
    });
});
