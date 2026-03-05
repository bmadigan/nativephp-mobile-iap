<?php

namespace Native\Mobile\Iap\DTOs;

use Carbon\Carbon;

readonly class Entitlement
{
    public function __construct(
        public string $productId,
        public bool $isActive,
        public string $transactionId,
        public string $originalTransactionId,
        public ?Carbon $expiresAt = null,
    ) {}

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt->isPast();
    }

    public function isLifetime(): bool
    {
        return $this->expiresAt === null && $this->isActive;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $expiresAt = $data['expiresAt'] ?? $data['expires_at'] ?? null;

        return new self(
            productId: $data['productId'] ?? $data['product_id'] ?? '',
            isActive: (bool) ($data['isActive'] ?? $data['is_active'] ?? true),
            transactionId: $data['transactionId'] ?? $data['transaction_id'] ?? '',
            originalTransactionId: $data['originalTransactionId'] ?? $data['original_transaction_id'] ?? $data['transactionId'] ?? $data['transaction_id'] ?? '',
            expiresAt: $expiresAt !== null ? ($expiresAt instanceof Carbon ? $expiresAt : Carbon::parse($expiresAt)) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'isActive' => $this->isActive,
            'transactionId' => $this->transactionId,
            'originalTransactionId' => $this->originalTransactionId,
            'expiresAt' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
