<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

/** A backed enum whose cases have a translation key for display. */
interface TranslatableEnum extends \BackedEnum
{
    public function translationKey(): string;
}
