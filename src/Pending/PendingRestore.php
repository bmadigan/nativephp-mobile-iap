<?php

namespace Native\Mobile\Iap\Pending;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Native\Mobile\Iap\Events\RestoreCompleted;

class PendingRestore
{
    protected ?string $id = null;

    protected ?string $eventClass = null;

    protected bool $started = false;

    public function __construct(
        protected bool $isNative = false
    ) {
        $this->eventClass = RestoreCompleted::class;
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
        session()->flash('_native_iap_restore_id', $this->getId());

        return $this;
    }

    public static function lastId(): ?string
    {
        return session('_native_iap_restore_id');
    }

    public function start(): bool
    {
        if ($this->started) {
            return false;
        }

        $this->started = true;

        if ($this->isNative && function_exists('nativephp_call')) {
            $payload = [
                'id' => $this->getId(),
                'event' => $this->eventClass,
            ];

            $result = nativephp_call('Iap.Restore', json_encode($payload));

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

    public function getEventClass(): ?string
    {
        return $this->eventClass;
    }
}
