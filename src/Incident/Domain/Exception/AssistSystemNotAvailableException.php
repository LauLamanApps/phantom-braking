<?php

declare(strict_types=1);

namespace App\Incident\Domain\Exception;

/** A make-specific assist system (e.g. Tesla Assisted Driving) was reported for another make. */
final class AssistSystemNotAvailableException extends \DomainException
{
}
