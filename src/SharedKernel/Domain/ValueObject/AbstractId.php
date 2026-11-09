<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

/**
 * UUIDv4 identifier. Generated in the domain so an entity is valid (and
 * addressable) before it is flushed, and so public URLs cannot be enumerated.
 */
abstract class AbstractId implements \Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    final private function __construct(private readonly string $value)
    {
    }

    public static function generate(): static
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
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
