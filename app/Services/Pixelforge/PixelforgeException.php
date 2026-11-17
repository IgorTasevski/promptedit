<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\Response;
use RuntimeException;

class PixelforgeException extends RuntimeException
{
    private const string UNREACHABLE = 'unreachable';

    private const array RETRYABLE_CODES = ['rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded', self::UNREACHABLE];

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $retryAfter = $response->header('Retry-After');

        return new self(
            $response->json('error.code', 'unknown_error'),
            $response->json('error.message', 'Pixelforge returned an unexpected response.'),
            $response->status(),
            $retryAfter === '' ? null : (int) $retryAfter,
        );
    }

    public static function unreachable(): self
    {
        return new self(self::UNREACHABLE, 'Pixelforge could not be reached.', 0);
    }

    public function userMessage(): string
    {
        return match ($this->errorCode) {
            'daily_limit_exceeded' => 'Pixelforge has used up today’s image quota. Try again after midnight UTC. You were not charged.',
            default => $this->getMessage(),
        };
    }

    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE_CODES, true) || $this->status >= 500;
    }
}
