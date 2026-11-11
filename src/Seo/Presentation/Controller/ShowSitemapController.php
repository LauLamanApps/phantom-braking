<?php

declare(strict_types=1);

namespace App\Seo\Presentation\Controller;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentFilter;
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
        ['changelog', []],
        ['legal', ['page' => 'privacy']],
        ['legal', ['page' => 'cookies']],
        ['legal', ['page' => 'terms']],
    ];

    /** @param list<string> $locales */
    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $locales,
    ) {
    }

    public function __invoke(): Response
    {
        $pages = array_map(static fn (array $page): array => ['route' => $page[0], 'params' => $page[1], 'lastmod' => null], self::PAGES);
        foreach ($this->incidents->getPage(IncidentFilter::all(), 1, self::MAX_REPORTS) as $incident) {
            $pages[] = ['route' => 'incident_show', 'params' => ['id' => (string) $incident->getId()], 'lastmod' => $incident->getReportedAt()];
        }

        $response = $this->render('seo/sitemap.xml.twig', ['pages' => $pages, 'locales' => $this->locales]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response->setPublic()->setMaxAge(3600);
    }
}
