<?php

namespace Native\Mobile\Iap\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RestoreCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, \Native\Mobile\Iap\DTOs\Purchase>  $purchases
     */
    public function __construct(
        public Collection $purchases,
        public ?string $id = null
    ) {}
}
