<?php

namespace Native\Mobile\Iap\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchasePending
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $productId,
        public ?string $transactionId = null,
        public ?string $id = null
    ) {}
}
