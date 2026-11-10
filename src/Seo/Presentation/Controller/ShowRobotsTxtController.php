<?php

declare(strict_types=1);

namespace App\Seo\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/robots.txt', name: 'robots_txt', format: 'txt', methods: ['GET'])]
final class ShowRobotsTxtController extends AbstractController
{
    public function __invoke(): Response
    {
        $response = $this->render('seo/robots.txt.twig');
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $response->setPublic()->setMaxAge(86400);
    }
}
