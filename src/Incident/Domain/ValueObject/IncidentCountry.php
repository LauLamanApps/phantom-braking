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

    /** The country's mainland time zone (Europe/Madrid, not the Canaries), to turn a reported local time into an instant. */
    public function timezone(): \DateTimeZone
    {
        if ('CY' === $this->code) {
            return new \DateTimeZone('Asia/Nicosia'); // listed under Asia, and Famagusta comes first
        }

        $zones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $this->code);
        $european = array_values(array_filter($zones, static fn (string $zone): bool => str_starts_with($zone, 'Europe/')));

        return new \DateTimeZone($european[0] ?? $zones[0] ?? 'UTC');
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
