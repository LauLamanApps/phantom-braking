<?php

declare(strict_types=1);

namespace App\Legal\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Privacy statement, cookie notice and terms of use; one hand-written template per page and language. */
#[Route('/{_locale}/{page}', name: 'legal', requirements: ['_locale' => '%app.locales%', 'page' => 'privacy|cookies|terms'], methods: ['GET'])]
final class ShowLegalPageController extends AbstractController
{
    /** Bump when the content of a page changes materially. */
    public const string LAST_UPDATED = '2026-11-09';

    public function __invoke(Request $request, string $page): Response
    {
        return $this->render(sprintf('legal/%s.%s.html.twig', $page, $request->getLocale()), [
            'page' => $page,
            'last_updated' => new \DateTimeImmutable(self::LAST_UPDATED),
        ]);
    }
}
