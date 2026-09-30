<?php

declare(strict_types=1);

namespace App\Services\Push;

/**
 * The outcome of one delivery attempt.
 *
 * The distinction that matters is permanent versus transient: a permanent failure
 * means the token will never work again (the app was uninstalled, the token was
 * rotated), so it is pruned; a transient one means try again later, and pruning on
 * it would silently unsubscribe a working device.
 */
final readonly class PushResult
{
    private function __construct(
        public bool $delivered,
        public bool $permanent,
        public bool $skipped,
        public string $detail,
    ) {}

    public static function delivered(string $detail = 'accepted'): self
    {
        return new self(delivered: true, permanent: false, skipped: false, detail: $detail);
    }

    /** The token is dead: stop using it. */
    public static function permanent(string $detail): self
    {
        return new self(delivered: false, permanent: true, skipped: false, detail: $detail);
    }

    /** Could not be sent, but the token may still be good. */
    public static function transient(string $detail): self
    {
        return new self(delivered: false, permanent: false, skipped: false, detail: $detail);
    }

    /** Nothing was attempted — push is off, or the transport has no credentials. */
    public static function skipped(string $detail): self
    {
        return new self(delivered: false, permanent: false, skipped: true, detail: $detail);
    }

    public static function failed(string $detail, bool $permanent): self
    {
        return $permanent ? self::permanent($detail) : self::transient($detail);
    }

    /** Whether this attempt means the token should be forgotten. */
    public function shouldPrune(): bool
    {
        return $this->permanent;
    }
}
