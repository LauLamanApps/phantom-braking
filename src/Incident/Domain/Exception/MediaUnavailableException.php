<?php

declare(strict_types=1);

namespace App\Incident\Domain\Exception;

/** The media storage could not be reached; trying again later may work. */
final class MediaUnavailableException extends \RuntimeException
{
}
