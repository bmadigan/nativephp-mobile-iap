<?php

use Native\Mobile\Iap\DTOs\Entitlement;

describe('Entitlement DTO', function () {
    it('creates from array with all fields', function () {
        $e = Entitlement::fromArray([
            'productId' => 'test', 'isActive' => true, 'transactionId' => 'txn_123',
            'originalTransactionId' => 'orig_123', 'expiresAt' => '2025-12-31T23:59:59+00:00',
        ]);
        expect($e->productId)->toBe('test')->and($e->expiresAt)->not->toBeNull();
    });
    it('is lifetime when active with no expiry', function () {
        $e = new Entitlement('lifetime', true, 'txn', 'orig');
        expect($e->isLifetime())->toBeTrue()->and($e->isExpired())->toBeFalse();
    });
    it('is expired when expiry date is past', function () {
        $e = new Entitlement('monthly', true, 'txn', 'orig', now()->subDay());
        expect($e->isExpired())->toBeTrue();
    });
});
