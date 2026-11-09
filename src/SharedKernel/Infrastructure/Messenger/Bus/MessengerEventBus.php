<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messenger\Bus;

use App\SharedKernel\Domain\Messenger\Bus\EventBusInterface;
use App\SharedKernel\Domain\Messenger\Transport\Event;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerEventBus implements EventBusInterface
{
    public function __construct(#[Autowire(service: 'event.bus')] private MessageBusInterface $bus)
    {
    }

    public function dispatch(Event $event): void
    {
        $this->bus->dispatch($event);
    }
}
