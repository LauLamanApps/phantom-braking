<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Filter;

use App\SharedKernel\Domain\ValueObject\TranslatableEnum;

/** Quick period choices in the filter bar; Custom uses the from/to dates. */
enum Period: string implements TranslatableEnum
{
    case All = 'all';
    case Last30Days = '30d';
    case Last90Days = '90d';
    case Last12Months = '12m';
    case ThisYear = 'ytd';
    case Custom = 'custom';

    public function translationKey(): string
    {
        return 'filter.period.'.$this->value;
    }

    /** @return array{\DateTimeImmutable|null, \DateTimeImmutable|null} */
    public function range(\DateTimeImmutable $now, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $today = $now->setTime(0, 0);

        return match ($this) {
            self::All => [null, null],
            self::Last30Days => [$today->modify('-29 days'), $today],
            self::Last90Days => [$today->modify('-89 days'), $today],
            self::Last12Months => [$today->modify('-1 year +1 day'), $today],
            self::ThisYear => [$today->modify('first day of january this year'), $today],
            self::Custom => [$from, $to],
        };
    }
}
