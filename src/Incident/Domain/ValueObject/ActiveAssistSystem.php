<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/** Which system was engaged. "None" means it came from the always-on AEB. */
enum ActiveAssistSystem: string implements TranslatableEnum
{
    case None = 'none';
    case CruiseControl = 'cruise';
    case AdaptiveCruise = 'acc';
    case AdaptiveCruiseWithLaneCentering = 'acc_lane';
    /** Tesla's supervised driving system, formerly "Full Self-Driving (FSD)". */
    case TeslaAssistedDriving = 'tesla_tad';
    case Unknown = 'unknown';

    public function translationKey(): string
    {
        return 'enum.assist.'.$this->value;
    }

    /** The make (as the registry spells it, upper case) this system exists for, or null when any car can have it. */
    public function requiredMake(): ?string
    {
        return match ($this) {
            self::TeslaAssistedDriving => 'TESLA',
            default => null,
        };
    }

    public function isAvailableFor(string $make): bool
    {
        $required = $this->requiredMake();

        return null === $required || mb_strtoupper(trim($make)) === $required;
    }
}
