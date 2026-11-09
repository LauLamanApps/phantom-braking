<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Presentation\Filter\FilterBarFactory;
use App\Incident\Presentation\Filter\IncidentFilterQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/reports', name: 'incident_list', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
final class ListIncidentsController extends AbstractController
{
    private const int PER_PAGE = 25;

    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly FilterBarFactory $filterBar,
    ) {
    }

    public function __invoke(IncidentFilterQuery $filter, #[MapQueryParameter] int $page = 1): Response
    {
        $now = new \DateTimeImmutable();
        $criteria = $filter->toFilter($now);
        $page = max(1, $page);
        $total = $this->incidents->count($criteria);

        return $this->render('incident/list.html.twig', [
            'filter_bar' => $this->filterBar->create($filter, $now),
            'incidents' => $this->incidents->getPage($criteria, $page, self::PER_PAGE),
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'total' => $total,
        ]);
    }
}
