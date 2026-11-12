<?php

declare(strict_types=1);

namespace App\Statistics\Presentation\Controller;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Statistics\Domain\Service\IncidentStatisticsInterface;
use App\Statistics\Presentation\Chart\StatisticsChartFactory;
use App\Statistics\Presentation\VehicleModelSlug;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** All statistics for one make and model, at a URL people can find and share. */
#[Route('/{_locale}/vehicles/{make}/{model}', name: 'vehicle_model', requirements: ['_locale' => '%app.locales%', 'make' => '[a-z0-9-]+', 'model' => '[a-z0-9-]+'], methods: ['GET'])]
final class ShowVehicleModelController extends AbstractController
{
    /** Fewer reports than this is too thin a page for search engines; it is still shown to visitors. */
    public const int INDEX_FROM_REPORTS = 3;

    public function __construct(
        private readonly IncidentStatisticsInterface $statistics,
        private readonly IncidentRepositoryInterface $incidents,
        private readonly StatisticsChartFactory $charts,
    ) {
    }

    public function __invoke(string $make, string $model): Response
    {
        $vehicle = $this->find($make, $model) ?? throw $this->createNotFoundException();
        $filter = new IncidentFilter(make: $vehicle['make'], model: $vehicle['model']);
        $stats = $this->statistics->overview($filter, $now = new \DateTimeImmutable());

        return $this->render('statistics/vehicle_model.html.twig', [
            'vehicle' => $vehicle,
            'stats' => $stats,
            'totals' => $stats->totals,
            'software_versions' => $this->statistics->softwareVersions($filter),
            'recent' => $this->incidents->getPage($filter, 1, 5),
            'indexable' => $stats->totals->reports >= self::INDEX_FROM_REPORTS,
            'filter_query' => ['make' => $vehicle['make'], 'model' => $vehicle['model']],
            'charts' => [
                'trend' => $this->charts->trend($stats->byMonth),
                'situation' => $this->charts->enumBars($stats->dimension('road_situation'), 'enum.road_situation.'),
                'trigger' => $this->charts->enumBars($stats->dimension('trigger'), 'enum.trigger.'),
                'assist' => $this->charts->enumBars($stats->dimension('assist'), 'enum.assist.'),
                'sun' => $this->charts->enumBars($stats->measured('sun'), 'measured.sun.'),
                'year' => $this->charts->bars(array_map('strval', array_keys($stats->byFirstAdmissionYear)), array_values($stats->byFirstAdmissionYear)),
            ],
        ]);
    }

    /** @return array{make: string, model: string, reports: int, vehicles: int}|null the most reported model whose slugs match */
    private function find(string $make, string $model): ?array
    {
        foreach ($this->statistics->vehicleModels(IncidentFilter::all()) as $vehicle) {
            if (VehicleModelSlug::params($vehicle['make'], $vehicle['model']) === ['make' => $make, 'model' => $model]) {
                return $vehicle;
            }
        }

        return null;
    }
}
