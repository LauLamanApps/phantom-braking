<?php

declare(strict_types=1);

namespace App\Admin\Domain\CommandHandler;

use App\Admin\Domain\Command\CreateAdminCommand;
use App\Admin\Domain\Exception\AdminAlreadyExistsException;
use App\Admin\Domain\Model\AdminUser;
use App\Admin\Domain\Repository\AdminUserRepositoryInterface;
use App\Admin\Domain\ValueObject\AdminUserId;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;

final readonly class CreateAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private AdminUserRepositoryInterface $admins,
    ) {
    }

    public function __invoke(CreateAdminCommand $command): void
    {
        if ($this->admins->existsWithName($command->name)) {
            throw new AdminAlreadyExistsException('An admin with this name already exists.');
        }

        $this->admins->persist(new AdminUser(AdminUserId::generate(), $command->name));
        $this->admins->flush();
    }
}
