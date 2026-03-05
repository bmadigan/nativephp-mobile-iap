<?php

namespace Native\Mobile\Iap\Pending;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Native\Mobile\Iap\Events\PurchaseCompleted;

class PendingPurchase
{
    protected ?string $id = null;

    protected ?string $eventClass = null;

    protected bool $started = false;

    public function __construct(
        protected string $productId,
        protected bool $isNative = false
    ) {
        $this->eventClass = PurchaseCompleted::class;
    }

    public function id(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        if ($this->id === null) {
            $this->id = (string) Str::uuid();
        }

        return $this->id;
    }

    public function event(string $eventClass): self
    {
        if (! class_exists($eventClass)) {
            throw new InvalidArgumentException("Event class {$eventClass} does not exist");
        }

        $this->eventClass = $eventClass;

        return $this;
    }

    public function remember(): self
    {
        session()->flash('_native_iap_purchase_id', $this->getId());

        return $this;
    }

    public static function lastId(): ?string
    {
        return session('_native_iap_purchase_id');
    }

    public function start(): bool
    {
        if ($this->started) {
            return false;
        }

        $this->started = true;

        if ($this->isNative && function_exists('nativephp_call')) {
            $payload = [
                'productId' => $this->productId,
                'id' => $this->getId(),
                'event' => $this->eventClass,
            ];

            $result = nativephp_call('Iap.Purchase', json_encode($payload));

            if ($result) {
                $decoded = json_decode($result, true);

                return isset($decoded['status']) && $decoded['status'] === 'success';
            }
        }

        return false;
    }

    public function __destruct()
    {
        if (! $this->started && $this->isNative) {
            $this->start();
        }
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getEventClass(): ?string
    {
        return $this->eventClass;
    }
}
