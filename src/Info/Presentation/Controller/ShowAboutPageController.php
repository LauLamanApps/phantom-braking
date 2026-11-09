<?php

declare(strict_types=1);

namespace App\Info\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/about', name: 'about', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
final class ShowAboutPageController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('info/about.html.twig');
    }
}
