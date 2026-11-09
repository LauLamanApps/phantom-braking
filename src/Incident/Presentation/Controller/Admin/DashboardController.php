<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller\Admin;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Presentation\Admin\StorageMonitor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** The two moderation queues: media awaiting review, and deletion requests from reporters. */
#[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly StorageMonitor $storage,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'pending' => $this->incidents->getWithPendingMedia(),
            'deletion_requests' => $this->incidents->getWithDeletionRequests(),
            'storage' => $this->storage->report(),
        ]);
    }
}
