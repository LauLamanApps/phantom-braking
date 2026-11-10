<?php

declare(strict_types=1);

namespace App\Info\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** CHANGELOG.md from the repository root, the same file GitHub shows. */
#[Route('/{_locale}/changelog', name: 'changelog', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
final class ShowChangelogController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/CHANGELOG.md')]
        private readonly string $changelogPath,
    ) {
    }

    public function __invoke(): Response
    {
        $markdown = (string) file_get_contents($this->changelogPath);

        // The page has its own (translated) title and intro: drop the file's heading and intro paragraph.
        // An empty "Unreleased" section is left out too.
        $markdown = (string) preg_replace('/\A# .*?\n\n.*?\n\n(?=## )/s', '', $markdown);
        $markdown = (string) preg_replace('/^## \[Unreleased\]\s*\n(?=## |\[)/m', '', $markdown);

        return $this->render('info/changelog.html.twig', ['changelog' => $markdown]);
    }
}
