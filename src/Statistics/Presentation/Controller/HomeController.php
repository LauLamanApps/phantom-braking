<?php

declare(strict_types=1);

namespace App\Statistics\Presentation\Controller;

use App\Incident\Presentation\Filter\FilterBarFactory;
use App\Incident\Presentation\Filter\IncidentFilterQuery;
use App\Statistics\Domain\Service\IncidentStatisticsInterface;
use App\Statistics\Presentation\Chart\StatisticsChartFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', name: 'home', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
final class HomeController extends AbstractController
{
    public function __construct(
        private readonly IncidentStatisticsInterface $statistics,
        private readonly StatisticsChartFactory $charts,
        private readonly FilterBarFactory $filterBar,
    ) {
    }

    public function __invoke(IncidentFilterQuery $filter): Response
    {
        $now = new \DateTimeImmutable();
        $stats = $this->statistics->overview($filter->toFilter($now), $now);

        return $this->render('statistics/home.html.twig', [
            'filter_bar' => $this->filterBar->create($filter, $now),
            'stats' => $stats,
            'totals' => $stats->totals,
            'charts' => [
                'trend' => $this->charts->trend($stats->byMonth),
                'situation' => $this->charts->enumBars($stats->dimension('road_situation'), 'enum.road_situation.'),
                'roadType' => $this->charts->enumBars($stats->dimension('road_type'), 'enum.road_type.'),
                'trigger' => $this->charts->enumBars($stats->dimension('trigger'), 'enum.trigger.'),
                'severity' => $this->charts->enumBars($stats->dimension('severity'), 'enum.severity.'),
                'consequence' => $this->charts->enumBars($stats->dimension('consequence'), 'enum.consequence.'),
                'assist' => $this->charts->enumBars($stats->dimension('assist'), 'enum.assist.'),
                'light' => $this->charts->enumBars($stats->dimension('light'), 'enum.light.'),
                'weather' => $this->charts->enumBars($stats->dimension('weather'), 'enum.weather.'),
                'dealer' => $this->charts->enumBars($stats->dimension('dealer'), 'enum.dealer.'),
                'sun' => $this->charts->enumBars($stats->measured('sun'), 'measured.sun.'),
                'temperature' => $this->charts->enumBars($stats->measured('temperature'), 'measured.temperature.'),
                'precipitation' => $this->charts->enumBars($stats->measured('precipitation'), 'measured.precipitation.'),
                'make' => $this->charts->bars(array_map('strval', array_keys($stats->byMake)), array_values($stats->byMake), horizontal: true),
                'hour' => $this->charts->bars(array_map(static fn (int $h): string => sprintf('%02d', $h), array_keys($stats->byHour)), array_values($stats->byHour)),
                'speed' => $this->charts->bars(array_map(static fn (string $b): string => $b.' km/h', array_keys($stats->bySpeed)), array_values($stats->bySpeed)),
                'year' => $this->charts->bars(array_map('strval', array_keys($stats->byFirstAdmissionYear)), array_values($stats->byFirstAdmissionYear)),
            ],
        ]);
    }
}
