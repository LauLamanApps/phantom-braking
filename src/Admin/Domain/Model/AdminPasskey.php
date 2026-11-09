<?php

declare(strict_types=1);

namespace App\Admin\Domain\Model;

use App\Admin\Domain\ValueObject\AdminPasskeyId;
use App\Admin\Domain\ValueObject\AdminUserId;
use Doctrine\ORM\Mapping as ORM;
use Webauthn\CredentialRecord;

/**
 * A passkey (WebAuthn credential) an admin logs in with. The public key and counter fields come from the
 * WebAuthn library's CredentialRecord (mapped superclass); only the public key is stored, never a secret.
 */
#[ORM\Entity]
#[ORM\Table(name: 'admin_passkey')]
#[ORM\Index(name: 'idx_admin_passkey_admin', columns: ['admin_user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_admin_passkey_credential', columns: ['public_key_credential_id'])]
class AdminPasskey extends CredentialRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'admin_passkey_id')]
    private AdminPasskeyId $id;

    #[ORM\Column(type: 'admin_user_id')]
    private AdminUserId $adminUserId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public static function register(AdminPasskeyId $id, AdminUserId $adminUserId, CredentialRecord $record, \DateTimeImmutable $now): self
    {
        if ($record->userHandle !== (string) $adminUserId) {
            throw new \DomainException('This passkey was created for another admin.');
        }

        $passkey = new self(
            $record->publicKeyCredentialId,
            $record->type,
            $record->transports,
            $record->attestationType,
            $record->trustPath,
            $record->aaguid,
            $record->credentialPublicKey,
            $record->userHandle,
            $record->counter,
            $record->otherUI,
            $record->backupEligible,
            $record->backupStatus,
            $record->uvInitialized,
        );
        $passkey->id = $id;
        $passkey->adminUserId = $adminUserId;
        $passkey->createdAt = $now;

        return $passkey;
    }

    /** After a successful login: keep the signature counter and backup state the library verified. */
    public function recordUse(CredentialRecord $verified, \DateTimeImmutable $now): void
    {
        if ($verified->publicKeyCredentialId !== $this->publicKeyCredentialId) {
            throw new \DomainException('Another passkey was used.');
        }
        $this->counter = $verified->counter;
        $this->backupStatus = $verified->backupStatus;
        $this->uvInitialized = $verified->uvInitialized;
        $this->lastUsedAt = $now;
    }

    public function getId(): AdminPasskeyId
    {
        return $this->id;
    }

    public function getAdminUserId(): AdminUserId
    {
        return $this->adminUserId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    /** Synced passkeys (iCloud Keychain, Google Password Manager, …) survive losing one device. */
    public function isSynced(): bool
    {
        return true === $this->backupStatus;
    }
}
