<?php

declare(strict_types=1);

namespace App\Admin\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/** The passkey login page; the ceremony itself runs in passkey_controller.js against the firewall's webauthn endpoints. */
#[Route('/admin/login', name: 'admin_login', methods: ['GET'])]
final class LoginController extends AbstractController
{
    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        // Set when a login link was expired or already used.
        return $this->render('admin/login.html.twig', ['error' => $authenticationUtils->getLastAuthenticationError()]);
    }
}
