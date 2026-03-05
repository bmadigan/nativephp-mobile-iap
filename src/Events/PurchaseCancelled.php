<?php

namespace Native\Mobile\Iap\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $productId,
        public ?string $id = null
    ) {}
}
