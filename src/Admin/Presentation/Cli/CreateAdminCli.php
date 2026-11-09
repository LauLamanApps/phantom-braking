<?php

declare(strict_types=1);

namespace App\Admin\Presentation\Cli;

use App\Admin\Domain\Command\CreateAdminCommand;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[AsCommand(name: 'app:admin:create', description: 'Create an admin (moderator) and print a one-time login link to add a passkey.')]
final readonly class CreateAdminCli
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private LoginLinkCli $loginLink,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Name, e.g. "Laurens"')] string $name): int
    {
        try {
            $this->commandBus->dispatch(new CreateAdminCommand($name));
        } catch (HandlerFailedException $e) {
            $io->error($e->getPrevious()?->getMessage() ?? $e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Admin %s created. There is no password: open the link below and add a passkey.', $name));

        return ($this->loginLink)($io, $name);
    }
}
