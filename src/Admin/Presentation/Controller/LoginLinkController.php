<?php

declare(strict_types=1);

namespace App\Admin\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Opening a one-time login link (app:admin:login-link) shows a button; only its POST is handled by the
 * firewall's login_link authenticator, so a link preview or scanner can't use up the link.
 */
#[Route('/admin/login/link', name: 'admin_login_link', methods: ['GET', 'POST'])]
final class LoginLinkController extends AbstractController
{
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            throw new \LogicException('The login_link authenticator handles this POST.');
        }

        return $this->render('admin/login_link.html.twig', ['query' => $request->query->all()]);
    }
}
