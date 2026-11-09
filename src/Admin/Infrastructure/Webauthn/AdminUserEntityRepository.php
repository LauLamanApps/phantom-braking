<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Webauthn;

use App\Admin\Domain\Model\AdminUser;
use App\Admin\Domain\Repository\AdminUserRepositoryInterface;
use App\Admin\Domain\ValueObject\AdminUserId;
use App\SharedKernel\Domain\Repository\NoResultException;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * How the WebAuthn bundle sees an admin: the user handle stored in each passkey is the admin's id (a random UUID,
 * no personal data), the name is what the device's passkey manager shows for this account.
 */
final readonly class AdminUserEntityRepository implements PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(private AdminUserRepositoryInterface $admins)
    {
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        try {
            return self::toUserEntity($this->admins->getByName($username));
        } catch (NoResultException) {
            return null;
        }
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        try {
            return self::toUserEntity($this->admins->get(AdminUserId::fromString($userHandle)));
        } catch (NoResultException|\InvalidArgumentException) {
            return null;
        }
    }

    private static function toUserEntity(AdminUser $admin): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create($admin->getName(), (string) $admin->getId(), $admin->getName());
    }
}
