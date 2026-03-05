<?php

namespace Native\Mobile\Iap\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class EntitlementsUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, \Native\Mobile\Iap\DTOs\Entitlement>  $entitlements
     */
    public function __construct(
        public Collection $entitlements,
        public ?string $id = null
    ) {}
}
