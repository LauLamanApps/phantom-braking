<?php

declare(strict_types=1);

namespace App\Admin\Domain\Model;

use App\Admin\Domain\ValueObject\AdminUserId;
use Doctrine\ORM\Mapping as ORM;

/**
 * A moderator who logs in to /admin with a passkey (see AdminPasskey) to review media and handle deletion requests.
 * Only a name is stored: passkey login needs no e-mail address or password.
 */
#[ORM\Entity]
#[ORM\Table(name: 'admin_user')]
#[ORM\UniqueConstraint(name: 'uniq_admin_user_name', columns: ['name'])]
class AdminUser
{
    public const int MAX_NAME_LENGTH = 60;

    #[ORM\Column(length: self::MAX_NAME_LENGTH)]
    private string $name;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'admin_user_id')]
        private AdminUserId $id,
        string $name,
    ) {
        $this->name = self::normaliseName($name);
        $length = mb_strlen($this->name);
        if ($length < 2 || $length > self::MAX_NAME_LENGTH || 1 !== preg_match("/^[\\p{L}\\p{N}][\\p{L}\\p{N} .'-]*$/u", $this->name)) {
            throw new \InvalidArgumentException(sprintf('A name is 2 to %d letters, digits, spaces, dots, hyphens or apostrophes.', self::MAX_NAME_LENGTH));
        }
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function normaliseName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    public function getId(): AdminUserId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
