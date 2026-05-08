<?php

use Carbon\Carbon;
use Native\Mobile\Iap\DTOs\Purchase;
use Native\Mobile\Iap\Enums\PurchaseState;

describe('Purchase DTO', function () {
    it('creates from array with all fields', function () {
        $purchase = Purchase::fromArray([
            'productId' => 'test', 'transactionId' => 'txn_123',
            'originalTransactionId' => 'orig_123', 'state' => 'completed',
            'purchaseDate' => now()->toIso8601String(), 'isSandbox' => true,
            'signedPayload' => 'jwt_token', 'purchaseToken' => 'play_token',
            'signature' => 'play_signature', 'quantity' => 2,
        ]);
        expect($purchase->state)->toBe(PurchaseState::Completed)
            ->and($purchase->isSandbox)->toBeTrue()
            ->and($purchase->purchaseToken)->toBe('play_token')
            ->and($purchase->signature)->toBe('play_signature')
            ->and($purchase->quantity)->toBe(2);
    });
    it('is active for completed purchase without expiry', function () {
        $purchase = new Purchase('lifetime', 'txn', 'orig', PurchaseState::Completed, now());
        expect($purchase->isActive())->toBeTrue()->and($purchase->isExpired())->toBeFalse();
    });
    it('is expired for subscription with past expiry', function () {
        $purchase = new Purchase('monthly', 'txn', 'orig', PurchaseState::Completed, now()->subMonth(), now()->subDay());
        expect($purchase->isActive())->toBeFalse()->and($purchase->isExpired())->toBeTrue();
    });
    it('is not active for failed purchase', function () {
        $purchase = new Purchase('test', 'txn', 'orig', PurchaseState::Failed, now());
        expect($purchase->isActive())->toBeFalse();
    });
});
