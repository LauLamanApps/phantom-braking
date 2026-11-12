<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

/**
 * UUIDv7 identifier (RFC 9562): a millisecond timestamp followed by 74 random
 * bits, so new IDs sort by creation time (index-friendly) while public URLs
 * still cannot be enumerated. Generated in the domain so an entity is valid
 * (and addressable) before it is flushed. IDs created before v7 are v4 and
 * remain valid.
 */
abstract class AbstractId implements \Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[47][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    final private function __construct(private readonly string $value)
    {
    }

    public static function generate(): static
    {
        $milliseconds = (int) floor(microtime(true) * 1000);
        $bytes = substr(pack('J', $milliseconds), 2).random_bytes(10);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x70);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return new static(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)));
    }

    public static function fromString(string $value): static
    {
        $value = strtolower($value);
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid %s.', $value, static::class));
        }

        return new static($value);
    }

    public function equals(self $other): bool
    {
        return $other::class === static::class && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
