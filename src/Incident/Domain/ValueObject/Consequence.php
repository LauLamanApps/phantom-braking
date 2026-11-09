<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

enum Consequence: string implements TranslatableEnum
{
    case None = 'none';
    case DriverStartled = 'startled';
    case FollowerHadToBrake = 'follower_braked';
    case NearRearEnd = 'near_rear_end';
    case RearEndCollision = 'rear_end';
    case CollisionWithInjury = 'injury';

    public function translationKey(): string
    {
        return 'enum.consequence.'.$this->value;
    }

    public function isCollision(): bool
    {
        return self::RearEndCollision === $this || self::CollisionWithInjury === $this;
    }

    /** The follower was forced to react: the hidden danger of phantom braking. */
    public function endangeredFollower(): bool
    {
        return \in_array($this, [self::FollowerHadToBrake, self::NearRearEnd, self::RearEndCollision, self::CollisionWithInjury], true);
    }
}
