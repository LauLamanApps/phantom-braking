<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Presentation\Form\WithdrawMediaType;
use App\SharedKernel\Domain\Repository\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/reports/{id}', name: 'incident_show', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
final class ShowIncidentController extends AbstractController
{
    public function __construct(private readonly IncidentRepositoryInterface $incidents)
    {
    }

    public function __invoke(IncidentId $id): Response
    {
        try {
            $incident = $this->incidents->get($id);
        } catch (NoResultException $e) {
            throw $this->createNotFoundException(previous: $e);
        }

        return $this->render('incident/show.html.twig', [
            'incident' => $incident,
            'withdraw_form' => $incident->hasMedia() && null === $incident->getMediaDeletionRequestedAt()
                ? $this->createForm(WithdrawMediaType::class, null, ['action' => $this->generateUrl('incident_request_media_deletion', ['id' => (string) $id])])
                : null,
        ]);
    }
}
