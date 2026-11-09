<?php

declare(strict_types=1);

namespace App\SharedKernel\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'root', methods: ['GET'])]
final class RedirectToLocaleController extends AbstractController
{
    /** @param list<string> $enabledLocales */
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $enabledLocales,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('home', [
            '_locale' => $request->getPreferredLanguage($this->enabledLocales) ?? 'nl',
        ]);
    }
}
