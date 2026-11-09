<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller\Admin;

use App\Incident\Domain\Command\ModeratePhotoCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\PhotoId;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/admin/photos/{id}/{decision}', name: 'admin_moderate_photo', requirements: ['decision' => 'approve|reject'], methods: ['POST'])]
final class ModeratePhotoController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly IncidentRepositoryInterface $incidents,
    ) {
    }

    #[IsCsrfTokenValid('admin', tokenKey: '_token')]
    public function __invoke(PhotoId $id, string $decision): RedirectResponse
    {
        $incidentId = $this->incidents->getByPhoto($id)->getId();

        try {
            $this->commandBus->dispatch(new ModeratePhotoCommand($id, 'approve' === $decision));
            $this->addFlash('success', 'admin.flash.photo_'.$decision.'d');
        } catch (HandlerFailedException $e) {
            $this->addFlash('error', $e->getPrevious()?->getMessage() ?? $e->getMessage());
        }

        return $this->redirectToRoute('admin_review', ['id' => (string) $incidentId, '_fragment' => 'photo-'.$id]);
    }
}
