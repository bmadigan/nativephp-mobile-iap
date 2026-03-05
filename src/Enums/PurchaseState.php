<?php

namespace Native\Mobile\Iap\Enums;

enum PurchaseState: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Deferred = 'deferred';

    public function isSuccessful(): bool
    {
        return $this === self::Completed;
    }

    public function isPending(): bool
    {
        return $this === self::Pending || $this === self::Deferred;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled, self::Refunded], true);
    }
}
