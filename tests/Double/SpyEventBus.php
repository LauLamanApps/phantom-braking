<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\SharedKernel\Domain\Messenger\Bus\EventBusInterface;
use App\SharedKernel\Domain\Messenger\Transport\Event;

final class SpyEventBus implements EventBusInterface
{
    /** @var list<Event> */
    public array $dispatched = [];

    public function dispatch(Event $event): void
    {
        $this->dispatched[] = $event;
    }
}
