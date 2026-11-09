<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

/**
 * The country where an incident happened (ISO 3166-1 alpha-2).
 *
 * Deliberately separate from the vehicle's registration country: a Dutch car
 * can phantom-brake on a German Autobahn. Any European country is allowed,
 * not only those whose vehicle register we can query.
 */
final readonly class IncidentCountry implements \Stringable
{
    /** EU 27, EEA, Switzerland, UK, the micro-states and the other European countries on the continent. */
    public const array EUROPE = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU',
        'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        'IS', 'LI', 'NO', 'CH', 'GB', 'AD', 'MC', 'SM', 'VA',
        'AL', 'BA', 'ME', 'MK', 'RS', 'MD', 'UA',
    ];

    private function __construct(public string $code)
    {
    }

    public static function fromString(string $code): self
    {
        $code = strtoupper(trim($code));
        if (!\in_array($code, self::EUROPE, true)) {
            throw new \InvalidArgumentException('Not a supported European country code.');
        }

        return new self($code);
    }

    public static function tryFromString(?string $code): ?self
    {
        return null !== $code && \in_array(strtoupper(trim($code)), self::EUROPE, true) ? self::fromString($code) : null;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
