<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\PhotoId;
use App\Incident\Presentation\Http\MediaResponder;
use App\SharedKernel\Domain\Repository\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Only moderated, consented photos are ever served, and only their plate-masked copy. */
#[Route('/photos/{id}', name: 'incident_photo', methods: ['GET'])]
final class StreamPhotoController extends AbstractController
{
    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly MediaResponder $responder,
    ) {
    }

    public function __invoke(Request $request, PhotoId $id): Response
    {
        try {
            $photo = array_values(array_filter(
                $this->incidents->getByPhoto($id)->getPublicPhotos(),
                static fn ($p): bool => $p->getId()->equals($id),
            ))[0] ?? throw $this->createNotFoundException();
        } catch (NoResultException $e) {
            throw $this->createNotFoundException(previous: $e);
        }

        return $this->responder->respond($request, (string) $photo->getPublishedPath(), $photo->getMimeType(), 'photo-'.$id.'.jpg', public: true);
    }
}
