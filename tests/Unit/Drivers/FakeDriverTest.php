<?php

use Illuminate\Support\Facades\Event;
use Native\Mobile\Iap\Drivers\FakeDriver;
use Native\Mobile\Iap\Enums\ProductType;
use Native\Mobile\Iap\Events\ProductsLoaded;
use Native\Mobile\Iap\Events\PurchaseCancelled;
use Native\Mobile\Iap\Events\PurchaseCompleted;
use Native\Mobile\Iap\Events\PurchaseFailed;
use Native\Mobile\Iap\Events\RestoreCompleted;

describe('FakeDriver', function () {
    beforeEach(function () {
        $this->driver = new FakeDriver;
        Event::fake();
    });

    describe('configuration', function () {
        it('can make payments by default', function () {
            expect($this->driver->canMakePayments())->toBeTrue();
        });
        it('can disable payments', function () {
            $this->driver->setCanMakePayments(false);
            expect($this->driver->canMakePayments())->toBeFalse();
        });
        it('is always available', function () {
            expect($this->driver->isAvailable())->toBeTrue();
        });
    });

    describe('mock products', function () {
        it('can mock products from arrays', function () {
            $this->driver->mockProducts([
                ['id' => 'product1', 'title' => 'Product 1', 'price' => '$9.99'],
                ['id' => 'product2', 'title' => 'Product 2', 'price' => '$19.99'],
            ]);
            $this->driver->products(['product1', 'product2'])->load();
            Event::assertDispatched(ProductsLoaded::class, function ($event) {
                return $event->products->count() === 2 && $event->products->first()->id === 'product1';
            });
        });
        it('can add mock products individually', function () {
            $this->driver->addMockProduct('lifetime', '$29.99', ProductType::NonConsumable)
                ->addMockProduct('monthly', '$4.99', ProductType::Subscription);
            $this->driver->products(['lifetime'])->load();
            Event::assertDispatched(ProductsLoaded::class, fn ($e) => $e->products->count() === 1);
        });
        it('reports invalid product ids', function () {
            $this->driver->addMockProduct('exists', '$9.99');
            $this->driver->products(['exists', 'does_not_exist'])->load();
            Event::assertDispatched(ProductsLoaded::class, function ($event) {
                return $event->products->count() === 1 && count($event->invalidIds) === 1
                    && $event->invalidIds[0] === 'does_not_exist';
            });
        });
    });

    describe('purchase flow', function () {
        it('succeeds by default', function () {
            $this->driver->purchase('test_product')->start();
            Event::assertDispatched(PurchaseCompleted::class, fn ($e) => $e->productId === 'test_product' && $e->isSandbox === true);
        });
        it('can be configured to fail', function () {
            $this->driver->shouldFail('PRODUCT_NOT_FOUND', 'Product not found');
            $this->driver->purchase('test_product')->start();
            Event::assertDispatched(PurchaseFailed::class, fn ($e) => $e->code === 'PRODUCT_NOT_FOUND');
        });
        it('can be configured to cancel', function () {
            $this->driver->shouldCancel();
            $this->driver->purchase('test_product')->start();
            Event::assertDispatched(PurchaseCancelled::class);
        });
        it('can switch back to success', function () {
            $this->driver->shouldFail()->shouldSucceed();
            $this->driver->purchase('test_product')->start();
            Event::assertDispatched(PurchaseCompleted::class);
            Event::assertNotDispatched(PurchaseFailed::class);
        });
    });

    describe('restore flow', function () {
        it('dispatches restore event with entitlements', function () {
            $this->driver->addMockEntitlement('lifetime', true)->addMockEntitlement('monthly', true);
            $this->driver->restore()->start();
            Event::assertDispatched(RestoreCompleted::class, fn ($e) => $e->purchases->count() === 2);
        });
        it('filters inactive entitlements from restore', function () {
            $this->driver->addMockEntitlement('active', true)->addMockEntitlement('inactive', false);
            $this->driver->restore()->start();
            Event::assertDispatched(RestoreCompleted::class, fn ($e) => $e->purchases->count() === 1);
        });
    });

    describe('assertions', function () {
        it('asserts product was purchased', function () {
            $this->driver->purchase('test_product')->start();
            $this->driver->assertPurchased('test_product');
        });
        it('asserts nothing was purchased', function () {
            $this->driver->assertNothingPurchased();
        });
        it('asserts restore was called', function () {
            $this->driver->addMockEntitlement('lifetime', true);
            $this->driver->restore()->start();
            $this->driver->assertRestored();
        });
        it('asserts products were loaded', function () {
            $this->driver->products(['product1', 'product2'])->load();
            $this->driver->assertProductsLoaded(['product1', 'product2']);
        });
    });
});
