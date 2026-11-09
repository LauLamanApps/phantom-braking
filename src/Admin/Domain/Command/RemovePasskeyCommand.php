<?php

declare(strict_types=1);

namespace App\Admin\Domain\Command;

use App\Admin\Domain\ValueObject\AdminPasskeyId;
use App\Admin\Domain\ValueObject\AdminUserId;
use App\SharedKernel\Domain\Messenger\Transport\Command;

final readonly class RemovePasskeyCommand implements Command
{
    public function __construct(
        public AdminUserId $adminUserId,
        public AdminPasskeyId $passkeyId,
    ) {
    }
}
