<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Messenger\Bus;

use App\SharedKernel\Domain\Messenger\Transport\Command;

interface CommandBusInterface
{
    public function dispatch(Command $command): void;
}
