<?php

declare(strict_types=1);

namespace App\Incident\Domain\Exception;

final class RangeNotSatisfiableException extends \RuntimeException
{
    public function __construct(public readonly int $totalSize)
    {
        parent::__construct('Requested range not satisfiable.');
    }
}
