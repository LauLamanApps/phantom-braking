<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/** Uploaded media (footage, photos) is never public until a moderator checked it for faces/plates of bystanders. */
enum ModerationStatus: string implements TranslatableEnum
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    /** The reporter withdrew consent. Final: never published again. */
    case Withdrawn = 'withdrawn';

    public function translationKey(): string
    {
        return 'enum.moderation_status.'.$this->value;
    }
}
