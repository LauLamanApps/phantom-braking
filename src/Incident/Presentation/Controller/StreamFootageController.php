<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Presentation\Http\MediaResponder;
use App\SharedKernel\Domain\Repository\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Only moderated, consented footage is ever served. Supports range requests for seeking (via MediaResponder). */
#[Route('/footage/{id}', name: 'incident_footage', methods: ['GET'])]
final class StreamFootageController extends AbstractController
{
    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly MediaResponder $responder,
    ) {
    }

    public function __invoke(Request $request, IncidentId $id): Response
    {
        try {
            $footage = $this->incidents->get($id)->getFootage();
        } catch (NoResultException $e) {
            throw $this->createNotFoundException(previous: $e);
        }

        if (!$footage->isPublic() || null === $footage->getPath()) {
            throw $this->createNotFoundException();
        }

        return $this->responder->respond($request, $footage->getPath(), (string) $footage->getMimeType(), 'dashcam-'.$id.'.'.pathinfo($footage->getPath(), \PATHINFO_EXTENSION), public: true);
    }
}
