<?php

declare(strict_types=1);

namespace App\Info\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/faq', name: 'faq', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
final class ShowFaqController extends AbstractController
{
    /**
     * Question ids per group, in display order. Each id has a `faq.<id>.q` and `faq.<id>.a` translation.
     *
     * @var array<string, list<string>>
     */
    public const array QUESTIONS = [
        'general' => ['what', 'who', 'why', 'official', 'representative'],
        'reporting' => ['account', 'countries', 'abroad', 'passenger', 'unsure'],
        'privacy' => ['plate', 'plate_needed', 'footage', 'withdraw', 'delete_report'],
        'data' => ['reuse', 'open_source'],
    ];

    public function __invoke(): Response
    {
        return $this->render('info/faq.html.twig', ['groups' => self::QUESTIONS]);
    }
}
