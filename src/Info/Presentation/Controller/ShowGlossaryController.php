<?php

declare(strict_types=1);

namespace App\Info\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/glossary', name: 'glossary', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
final class ShowGlossaryController extends AbstractController
{
    /**
     * Term ids; each has a `glossary.<id>.term` and `glossary.<id>.definition` translation.
     * The page lists them alphabetically per language.
     *
     * @var list<string>
     */
    public const array TERMS = [
        'phantom_braking', 'false_positive', 'aeb', 'fcw', 'adas', 'acc', 'lane_assist', 'tad', 'sensors',
        'gsr2', 'rdw', 'type_approval', 'eu_category', 'hybrid_class', 'recall', 'software_version', 'ilt',
        'sign_gantry', 'road_types', 'rear_end',
    ];

    public function __invoke(): Response
    {
        return $this->render('info/glossary.html.twig', ['terms' => self::TERMS]);
    }
}
