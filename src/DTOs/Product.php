<?php

namespace Native\Mobile\Iap\DTOs;

use Native\Mobile\Iap\Enums\ProductType;

readonly class Product
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $price,
        public float $priceAmount,
        public string $currency,
        public ProductType $type,
        public ?string $subscriptionPeriod = null,
    ) {}

    public function isSubscription(): bool
    {
        return $this->type->isSubscription();
    }

    public function isConsumable(): bool
    {
        return $this->type->isConsumable();
    }

    public function isNonConsumable(): bool
    {
        return $this->type->isNonConsumable();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'] ?? $data['name'] ?? '',
            description: $data['description'] ?? '',
            price: $data['price'] ?? '',
            priceAmount: (float) ($data['priceAmount'] ?? $data['price_amount'] ?? 0),
            currency: $data['currency'] ?? 'USD',
            type: isset($data['type']) && $data['type'] instanceof ProductType
                ? $data['type']
                : ProductType::tryFrom($data['type'] ?? 'non_consumable') ?? ProductType::NonConsumable,
            subscriptionPeriod: $data['subscriptionPeriod'] ?? $data['subscription_period'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'priceAmount' => $this->priceAmount,
            'currency' => $this->currency,
            'type' => $this->type->value,
            'subscriptionPeriod' => $this->subscriptionPeriod,
        ];
    }
}
