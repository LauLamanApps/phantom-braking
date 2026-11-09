<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller\Admin;

use App\Incident\Domain\Command\SavePhotoMasksCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\PhotoId;
use App\Incident\Domain\ValueObject\PlateMask;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/admin/photos/{id}/masks', name: 'admin_save_photo_masks', methods: ['POST'])]
final class SavePhotoMasksController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly IncidentRepositoryInterface $incidents,
    ) {
    }

    #[IsCsrfTokenValid('admin', tokenKey: '_token')]
    public function __invoke(Request $request, PhotoId $id): RedirectResponse
    {
        $incidentId = $this->incidents->getByPhoto($id)->getId();

        try {
            $masks = PlateMask::listFromArray(json_decode($request->getPayload()->getString('masks', '[]'), true, 4, \JSON_THROW_ON_ERROR));
        } catch (\JsonException|\InvalidArgumentException) {
            $this->addFlash('error', 'admin.flash.masks_invalid');

            return $this->redirectToRoute('admin_review', ['id' => (string) $incidentId, '_fragment' => 'photo-'.$id]);
        }

        $this->commandBus->dispatch(new SavePhotoMasksCommand($id, $masks));
        $this->addFlash('success', 'admin.flash.masks_saved');

        return $this->redirectToRoute('admin_review', ['id' => (string) $incidentId, '_fragment' => 'photo-'.$id]);
    }
}
