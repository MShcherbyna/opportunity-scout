<?php

namespace App\Enums;

enum ThreadsCallbackOutcome
{
    case StateMismatch;
    case AccessDenied;
    case ConnectFailed;
    case AccountTaken;
    case Connected;

    /**
     * The toast type for the outcome.
     */
    public function toastType(): string
    {
        return $this === self::Connected ? 'success' : 'error';
    }

    /**
     * The toast copy for the outcome.
     */
    public function toastMessage(): string
    {
        return match ($this) {
            self::StateMismatch => "We couldn't verify the Threads connection. Please try again.",
            self::AccessDenied => 'Threads access was not granted.',
            self::ConnectFailed => 'Connecting Threads failed. Please try again.',
            self::AccountTaken => 'This Threads account is already connected to another Opportunity Scout account.',
            self::Connected => 'Threads account connected.',
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
