<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/** How the dealer / importer responded. Kassa found many drivers being dismissed or blamed. */
enum DealerResponse: string implements TranslatableEnum
{
    case NotContacted = 'not_contacted';
    case Pending = 'pending';
    case Acknowledged = 'acknowledged';
    case SoftwareUpdate = 'software_update';
    case Recalibrated = 'recalibrated';
    case NoFaultFound = 'no_fault_found';
    case BlamedDriver = 'blamed_driver';
    case Refused = 'refused';

    public function translationKey(): string
    {
        return 'enum.dealer.'.$this->value;
    }

    public function contactedDealer(): bool
    {
        return self::NotContacted !== $this;
    }

    /** The dealer did not take the report seriously. */
    public function isDismissive(): bool
    {
        return \in_array($this, [self::NoFaultFound, self::BlamedDriver, self::Refused], true);
    }
}
