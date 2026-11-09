<?php

declare(strict_types=1);

namespace App\Admin\Domain\Command;

use App\SharedKernel\Domain\Messenger\Transport\Command;

final readonly class CreateAdminCommand implements Command
{
    public function __construct(
        public string $name,
    ) {
    }
}
