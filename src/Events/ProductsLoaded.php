<?php

namespace Native\Mobile\Iap\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ProductsLoaded
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, \Native\Mobile\Iap\DTOs\Product>  $products
     * @param  array<int, string>  $invalidIds
     */
    public function __construct(
        public Collection $products,
        public array $invalidIds = [],
        public ?string $id = null
    ) {}
}
