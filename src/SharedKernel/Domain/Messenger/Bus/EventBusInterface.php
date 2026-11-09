<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Messenger\Bus;

use App\SharedKernel\Domain\Messenger\Transport\Event;

interface EventBusInterface
{
    public function dispatch(Event $event): void;
}
