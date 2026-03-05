<?php

use Native\Mobile\Iap\Drivers\NullDriver;
use Native\Mobile\Iap\Pending\PendingProducts;
use Native\Mobile\Iap\Pending\PendingPurchase;
use Native\Mobile\Iap\Pending\PendingRestore;

describe('NullDriver', function () {
    beforeEach(function () { $this->driver = new NullDriver; });

    it('cannot make payments', fn () => expect($this->driver->canMakePayments())->toBeFalse());
    it('is not available', fn () => expect($this->driver->isAvailable())->toBeFalse());
    it('returns empty entitlements', fn () => expect($this->driver->entitlements())->toBeEmpty());
    it('has no entitlements', fn () => expect($this->driver->hasEntitlement('any'))->toBeFalse());
    it('returns PendingProducts builder', fn () => expect($this->driver->products(['p1']))->toBeInstanceOf(PendingProducts::class));
    it('returns PendingPurchase builder', fn () => expect($this->driver->purchase('p1'))->toBeInstanceOf(PendingPurchase::class));
    it('returns PendingRestore builder', fn () => expect($this->driver->restore())->toBeInstanceOf(PendingRestore::class));
    it('registers and merges products', function () {
        $this->driver->register(['p1' => ['name' => 'First']]);
        $this->driver->register(['p2' => ['name' => 'Second']]);
        expect($this->driver->getRegisteredProducts())->toHaveCount(2);
    });
});
