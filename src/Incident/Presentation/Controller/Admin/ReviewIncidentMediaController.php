<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller\Admin;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Repository\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reports/{id}', name: 'admin_review', methods: ['GET'])]
final class ReviewIncidentMediaController extends AbstractController
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

        return $this->render('admin/review.html.twig', ['incident' => $incident]);
    }
}
