<?php

declare(strict_types=1);

namespace App\Seo\Presentation\Controller;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentFilter;
use App\Statistics\Domain\Service\IncidentStatisticsInterface;
use App\Statistics\Presentation\Controller\ShowVehicleModelController;
use App\Statistics\Presentation\VehicleModelSlug;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Every public page in every language, each listing its translations (hreflang). */
#[Route('/sitemap.xml', name: 'sitemap', format: 'xml', methods: ['GET'])]
final class ShowSitemapController extends AbstractController
{
    /** A sitemap holds at most 50,000 URLs; each report counts once per language. */
    private const int MAX_REPORTS = 10000;

    private const array PAGES = [
        ['home', []],
        ['incident_list', []],
        ['incident_report', []],
        ['about', []],
        ['faq', []],
        ['glossary', []],
        ['vehicle_models', []],
        ['changelog', []],
        ['legal', ['page' => 'privacy']],
        ['legal', ['page' => 'cookies']],
        ['legal', ['page' => 'terms']],
    ];

    /** @param list<string> $locales */
    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly IncidentStatisticsInterface $statistics,
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $locales,
    ) {
    }

    public function __invoke(): Response
    {
        $pages = array_map(static fn (array $page): array => ['route' => $page[0], 'params' => $page[1], 'lastmod' => null], self::PAGES);
        $models = [];
        foreach ($this->statistics->vehicleModels(IncidentFilter::all()) as $model) {
            $params = VehicleModelSlug::params($model['make'], $model['model']);
            // Two spellings with the same slug ("ID.3", "ID 3") share a page: the most reported one, which comes first.
            if (null !== $params && $model['reports'] >= ShowVehicleModelController::INDEX_FROM_REPORTS && !isset($models[$key = implode('/', $params)])) {
                $models[$key] = true;
                $pages[] = ['route' => 'vehicle_model', 'params' => $params, 'lastmod' => null];
            }
        }
        foreach ($this->incidents->getPage(IncidentFilter::all(), 1, self::MAX_REPORTS) as $incident) {
            $pages[] = ['route' => 'incident_show', 'params' => ['id' => (string) $incident->getId()], 'lastmod' => $incident->getReportedAt()];
        }

        $response = $this->render('seo/sitemap.xml.twig', ['pages' => $pages, 'locales' => $this->locales]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response->setPublic()->setMaxAge(3600);
    }
}
