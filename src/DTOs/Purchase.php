<?php

namespace Native\Mobile\Iap\DTOs;

use Carbon\Carbon;
use Native\Mobile\Iap\Enums\PurchaseState;

readonly class Purchase
{
    public function __construct(
        public string $productId,
        public string $transactionId,
        public string $originalTransactionId,
        public PurchaseState $state,
        public Carbon $purchaseDate,
        public ?Carbon $expiresAt = null,
        public bool $isSandbox = false,
        public ?string $signedPayload = null,
        public ?string $purchaseToken = null,
        public ?string $signature = null,
        public ?int $quantity = null,
    ) {}

    public function isActive(): bool
    {
        if (! $this->state->isSuccessful()) {
            return false;
        }

        if ($this->expiresAt === null) {
            return true;
        }

        return $this->expiresAt->isFuture();
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt->isPast();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $purchaseDate = $data['purchaseDate'] ?? $data['purchase_date'] ?? now();
        $expiresAt = $data['expiresAt'] ?? $data['expires_at'] ?? null;

        return new self(
            productId: $data['productId'] ?? $data['product_id'] ?? '',
            transactionId: $data['transactionId'] ?? $data['transaction_id'] ?? '',
            originalTransactionId: $data['originalTransactionId'] ?? $data['original_transaction_id'] ?? $data['transactionId'] ?? $data['transaction_id'] ?? '',
            state: $data['state'] instanceof PurchaseState
                ? $data['state']
                : PurchaseState::tryFrom($data['state'] ?? 'completed') ?? PurchaseState::Completed,
            purchaseDate: $purchaseDate instanceof Carbon ? $purchaseDate : Carbon::parse($purchaseDate),
            expiresAt: $expiresAt !== null ? ($expiresAt instanceof Carbon ? $expiresAt : Carbon::parse($expiresAt)) : null,
            isSandbox: (bool) ($data['isSandbox'] ?? $data['is_sandbox'] ?? false),
            signedPayload: $data['signedPayload'] ?? $data['signed_payload'] ?? null,
            purchaseToken: $data['purchaseToken'] ?? $data['purchase_token'] ?? null,
            signature: $data['signature'] ?? null,
            quantity: isset($data['quantity']) ? (int) $data['quantity'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'transactionId' => $this->transactionId,
            'originalTransactionId' => $this->originalTransactionId,
            'state' => $this->state->value,
            'purchaseDate' => $this->purchaseDate->toIso8601String(),
            'expiresAt' => $this->expiresAt?->toIso8601String(),
            'isSandbox' => $this->isSandbox,
            'signedPayload' => $this->signedPayload,
            'purchaseToken' => $this->purchaseToken,
            'signature' => $this->signature,
            'quantity' => $this->quantity,
        ];
    }
}
