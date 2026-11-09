<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Admin\Domain\Model\AdminPasskey;
use App\Admin\Domain\ValueObject\AdminPasskeyId;
use App\Admin\Domain\ValueObject\AdminUserId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class AdminPasskeyTest extends TestCase
{
    public function testRegistersForTheAdminInTheUserHandleAndRecordsUse(): void
    {
        $admin = AdminUserId::generate();
        $record = self::record('credential-1', (string) $admin);
        $passkey = AdminPasskey::register(AdminPasskeyId::generate(), $admin, $record, new \DateTimeImmutable('2026-11-09 10:00'));

        self::assertSame('credential-1', $passkey->publicKeyCredentialId);
        self::assertTrue($passkey->getAdminUserId()->equals($admin));
        self::assertNull($passkey->getLastUsedAt());
        self::assertTrue($passkey->isSynced());

        $record->counter = 3;
        $passkey->recordUse($record, new \DateTimeImmutable('2026-11-10 09:00'));
        self::assertSame(3, $passkey->counter);
        self::assertEquals(new \DateTimeImmutable('2026-11-10 09:00'), $passkey->getLastUsedAt());

        $this->expectException(\DomainException::class);
        $passkey->recordUse(self::record('credential-2', (string) $admin), new \DateTimeImmutable());
    }

    public function testRefusesAPasskeyCreatedForAnotherAdmin(): void
    {
        $this->expectException(\DomainException::class);
        AdminPasskey::register(AdminPasskeyId::generate(), AdminUserId::generate(), self::record('x', (string) AdminUserId::generate()), new \DateTimeImmutable());
    }

    private static function record(string $credentialId, string $userHandle): CredentialRecord
    {
        return CredentialRecord::create($credentialId, 'public-key', ['internal'], 'none', EmptyTrustPath::create(), Uuid::v4(), 'public-key-bytes', $userHandle, 0, backupEligible: true, backupStatus: true);
    }
}
