<?php

namespace Native\Mobile\Iap\Enums;

enum ProductType: string
{
    case Consumable = 'consumable';
    case NonConsumable = 'non_consumable';
    case Subscription = 'subscription';

    public function isConsumable(): bool
    {
        return $this === self::Consumable;
    }

    public function isNonConsumable(): bool
    {
        return $this === self::NonConsumable;
    }

    public function isSubscription(): bool
    {
        return $this === self::Subscription;
    }
}
