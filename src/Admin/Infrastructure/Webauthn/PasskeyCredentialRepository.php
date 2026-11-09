<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Webauthn;

use App\Admin\Domain\Model\AdminPasskey;
use App\Admin\Domain\Repository\AdminPasskeyRepositoryInterface;
use App\Admin\Domain\ValueObject\AdminPasskeyId;
use App\Admin\Domain\ValueObject\AdminUserId;
use App\SharedKernel\Domain\Repository\NoResultException;
use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Where the WebAuthn bundle reads and writes passkeys. A record it saves is either a new passkey (after the
 * registration ceremony, for the logged-in admin) or an existing one with an updated counter (after a login).
 */
final readonly class PasskeyCredentialRepository implements CredentialRecordRepositoryInterface, CanSaveCredentialRecord
{
    public function __construct(private AdminPasskeyRepositoryInterface $passkeys)
    {
    }

    /** @return list<CredentialRecord> */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        try {
            return $this->passkeys->getAllForAdmin(AdminUserId::fromString($publicKeyCredentialUserEntity->id));
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
    {
        try {
            return $this->passkeys->getByCredentialId($publicKeyCredentialId);
        } catch (NoResultException) {
            return null;
        }
    }

    public function saveCredentialRecord(CredentialRecord $credentialRecord): void
    {
        $now = new \DateTimeImmutable();

        try {
            // After a login the bundle passes back the AdminPasskey it loaded, with the verified counter.
            $this->passkeys->getByCredentialId($credentialRecord->publicKeyCredentialId)->recordUse($credentialRecord, $now);
        } catch (NoResultException) {
            $this->passkeys->persist(AdminPasskey::register(
                AdminPasskeyId::generate(),
                AdminUserId::fromString($credentialRecord->userHandle),
                $credentialRecord,
                $now,
            ));
        }

        $this->passkeys->flush();
    }
}
