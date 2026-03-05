<?php

namespace Native\Mobile\Iap\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $productId,
        public string $code,
        public string $message,
        public ?string $id = null
    ) {}
}
