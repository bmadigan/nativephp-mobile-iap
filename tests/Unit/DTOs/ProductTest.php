<?php

use Native\Mobile\Iap\DTOs\Product;
use Native\Mobile\Iap\Enums\ProductType;

describe('Product DTO', function () {
    it('creates from array with all fields', function () {
        $product = Product::fromArray([
            'id' => 'test_product', 'title' => 'Test Product', 'description' => 'A test product',
            'price' => '$9.99', 'priceAmount' => 9.99, 'currency' => 'USD',
            'type' => 'non_consumable', 'subscriptionPeriod' => null,
        ]);
        expect($product->id)->toBe('test_product')
            ->and($product->type)->toBe(ProductType::NonConsumable);
    });
    it('handles snake_case field names', function () {
        $product = Product::fromArray(['id' => 'test', 'name' => 'Test Name', 'price_amount' => 19.99, 'subscription_period' => 'P1M']);
        expect($product->title)->toBe('Test Name')->and($product->subscriptionPeriod)->toBe('P1M');
    });
    it('defaults to non-consumable type', function () {
        expect(Product::fromArray(['id' => 'test'])->type)->toBe(ProductType::NonConsumable);
    });
    it('identifies product types correctly', function () {
        $sub = new Product('sub', '', '', '', 0, '', ProductType::Subscription, 'P1M');
        $con = new Product('con', '', '', '', 0, '', ProductType::Consumable);
        expect($sub->isSubscription())->toBeTrue()->and($con->isConsumable())->toBeTrue();
    });
});
