<?php

declare(strict_types=1);

namespace App\Domain\Payment\ValueObjects;

class PaymentResult
{
    private function __construct(
        private bool $success,
        private string $message,
    ) {}

    public static function successful(): self
    {
        return new self(true, 'Payment processed successfully.');
    }

    public static function failed(string $reason = 'Payment declined.'): self
    {
        return new self(false, $reason);
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
