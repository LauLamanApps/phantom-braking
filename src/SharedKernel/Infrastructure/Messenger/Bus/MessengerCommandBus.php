<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messenger\Bus;

use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use App\SharedKernel\Domain\Messenger\Transport\Command;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerCommandBus implements CommandBusInterface
{
    public function __construct(#[Autowire(service: 'command.bus')] private MessageBusInterface $bus)
    {
    }

    public function dispatch(Command $command): void
    {
        $this->bus->dispatch($command);
    }
}
