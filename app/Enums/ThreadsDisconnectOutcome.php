<?php

namespace App\Enums;

enum ThreadsDisconnectOutcome
{
    case NotConnected;
    case Disconnected;

    /**
     * The toast type for the outcome.
     */
    public function toastType(): string
    {
        return $this === self::Disconnected ? 'success' : 'info';
    }

    /**
     * The toast copy for the outcome.
     */
    public function toastMessage(): string
    {
        return match ($this) {
            self::NotConnected => 'Threads is not connected.',
            self::Disconnected => 'Threads account disconnected.',
        };
    }

    /**
     * The toast to flash for the outcome.
     *
     * @return array{type: string, message: string}
     */
    public function toast(): array
    {
        return ['type' => $this->toastType(), 'message' => $this->toastMessage()];
    }
}
