<?php

declare(strict_types=1);

namespace App\Admin\Domain\CommandHandler;

use App\Admin\Domain\Command\RemovePasskeyCommand;
use App\Admin\Domain\Exception\LastPasskeyException;
use App\Admin\Domain\Repository\AdminPasskeyRepositoryInterface;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;

final readonly class RemovePasskeyCommandHandler implements CommandHandlerInterface
{
    public function __construct(private AdminPasskeyRepositoryInterface $passkeys)
    {
    }

    public function __invoke(RemovePasskeyCommand $command): void
    {
        $passkey = $this->passkeys->get($command->passkeyId);
        if (!$passkey->getAdminUserId()->equals($command->adminUserId)) {
            throw new \DomainException('This passkey belongs to another admin.');
        }
        if ($this->passkeys->countForAdmin($command->adminUserId) <= 1) {
            throw new LastPasskeyException('You cannot remove your last passkey: you would lock yourself out.');
        }

        $this->passkeys->remove($passkey);
        $this->passkeys->flush();
    }
}
