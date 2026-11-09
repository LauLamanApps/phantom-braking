<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller\Admin;

use App\Incident\Presentation\Admin\StorageMonitor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/admin/storage/refresh', name: 'admin_refresh_storage', methods: ['POST'])]
final class RefreshStorageUsageController extends AbstractController
{
    public function __construct(private readonly StorageMonitor $monitor)
    {
    }

    #[IsCsrfTokenValid('admin', tokenKey: '_token')]
    public function __invoke(): RedirectResponse
    {
        $this->monitor->refresh();

        return $this->redirectToRoute('admin_dashboard', ['_fragment' => 'storage']);
    }
}
