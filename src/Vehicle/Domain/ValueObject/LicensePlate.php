<?php

declare(strict_types=1);

namespace App\Vehicle\Domain\ValueObject;

use App\Vehicle\Domain\Exception\InvalidLicensePlateException;

/**
 * A registration plate, normalised to upper-case alphanumerics
 * ("gv-n31-k" → "GVN31K"). Never persisted: we only keep a keyed
 * fingerprint of it, see PlateFingerprinterInterface.
 */
final readonly class LicensePlate implements \Stringable
{
    private function __construct(public string $value)
    {
    }

    public static function fromInput(string $input): self
    {
        $normalised = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));

        if (1 !== preg_match('/^[A-Z0-9]{1,10}$/', $normalised)) {
            // The input is not echoed: exception messages end up in logs.
            throw new InvalidLicensePlateException('Not a valid license plate.');
        }

        return new self($normalised);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
