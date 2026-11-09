<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/** How hard the car braked, in plain-language steps a driver can judge. */
enum BrakeSeverity: string implements TranslatableEnum
{
    case WarningOnly = 'warning_only';
    case Light = 'light';
    case Strong = 'strong';
    case Emergency = 'emergency';

    public function translationKey(): string
    {
        return 'enum.severity.'.$this->value;
    }

    public function actuallyBraked(): bool
    {
        return self::WarningOnly !== $this;
    }
}
