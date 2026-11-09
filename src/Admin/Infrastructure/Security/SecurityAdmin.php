<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Security;

use App\Admin\Domain\ValueObject\AdminUserId;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Symfony Security's view of an AdminUser; keeps the domain model free of framework interfaces.
 * There is no password: admins log in with a passkey, or with a one-time login link from the CLI.
 */
final readonly class SecurityAdmin implements UserInterface
{
    public function __construct(
        private string $id,
        private string $name,
    ) {
    }

    public function getAdminUserId(): AdminUserId
    {
        return AdminUserId::fromString($this->id);
    }

    public function getUserIdentifier(): string
    {
        return '' === $this->name ? throw new \LogicException('Admin without a name.') : $this->name;
    }

    public function getRoles(): array
    {
        return ['ROLE_ADMIN'];
    }
}
