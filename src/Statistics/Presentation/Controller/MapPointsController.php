<?php

declare(strict_types=1);

namespace App\Statistics\Presentation\Controller;

use App\Incident\Presentation\Filter\IncidentFilterQuery;
use App\Statistics\Domain\Service\IncidentStatisticsInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Takes the same query parameters as the dashboard (?country=NL&make=…). */
#[Route('/api/incidents/map', name: 'incident_map_points', methods: ['GET'])]
final class MapPointsController extends AbstractController
{
    public function __construct(private readonly IncidentStatisticsInterface $statistics)
    {
    }

    public function __invoke(IncidentFilterQuery $filter): JsonResponse
    {
        $response = $this->json($this->statistics->mapPoints($filter->toFilter(new \DateTimeImmutable())));
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }
}
