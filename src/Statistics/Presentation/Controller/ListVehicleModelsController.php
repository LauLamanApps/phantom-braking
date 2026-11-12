<?php

declare(strict_types=1);

namespace App\Statistics\Presentation\Controller;

use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Statistics\Domain\Service\IncidentStatisticsInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Every make and model with reports, each linking to its own page. */
#[Route('/{_locale}/vehicles', name: 'vehicle_models', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
final class ListVehicleModelsController extends AbstractController
{
    public function __construct(private readonly IncidentStatisticsInterface $statistics)
    {
    }

    public function __invoke(): Response
    {
        $byMake = [];
        foreach ($this->statistics->vehicleModels(IncidentFilter::all()) as $model) {
            $byMake[$model['make']][] = $model;
        }
        ksort($byMake, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $this->render('statistics/vehicle_models.html.twig', ['makes' => $byMake]);
    }
}
