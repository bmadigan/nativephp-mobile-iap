<?php

namespace Native\Mobile\Iap\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Native\Mobile\Iap\DTOs\Purchase;

class PurchaseCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $productId,
        public Purchase $purchase,
        public ?string $signedPayload = null,
        public ?string $id = null,
        public bool $isSandbox = false
    ) {}
}
