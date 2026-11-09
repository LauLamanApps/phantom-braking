<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Security;

use App\Admin\Domain\Model\AdminUser;
use App\Admin\Domain\Repository\AdminUserRepositoryInterface;
use App\SharedKernel\Domain\Repository\NoResultException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<SecurityAdmin> */
final readonly class AdminUserProvider implements UserProviderInterface
{
    public function __construct(private AdminUserRepositoryInterface $admins)
    {
    }

    public static function toSecurityUser(AdminUser $admin): SecurityAdmin
    {
        return new SecurityAdmin((string) $admin->getId(), $admin->getName());
    }

    public function loadUserByIdentifier(string $identifier): SecurityAdmin
    {
        try {
            return self::toSecurityUser($this->admins->getByName($identifier));
        } catch (NoResultException $e) {
            throw new UserNotFoundException(previous: $e);
        }
    }

    public function refreshUser(UserInterface $user): SecurityAdmin
    {
        if (!$user instanceof SecurityAdmin) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityAdmin::class === $class;
    }
}
