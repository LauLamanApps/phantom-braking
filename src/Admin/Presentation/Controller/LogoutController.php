<?php

declare(strict_types=1);

namespace App\Admin\Presentation\Controller;

use Symfony\Component\Routing\Attribute\Route;

/** Intercepted by the firewall's logout listener; never actually runs. */
#[Route('/admin/logout', name: 'admin_logout', methods: ['GET', 'POST'])]
final class LogoutController
{
    public function __invoke(): never
    {
        throw new \LogicException('Handled by the security firewall.');
    }
}
