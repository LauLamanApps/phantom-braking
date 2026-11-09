<?php

declare(strict_types=1);

namespace App\Admin\Presentation\Controller;

use App\Admin\Domain\Repository\AdminPasskeyRepositoryInterface;
use App\Admin\Infrastructure\Security\SecurityAdmin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/admin/passkeys', name: 'admin_passkeys', methods: ['GET'])]
final class ListPasskeysController extends AbstractController
{
    public function __construct(private readonly AdminPasskeyRepositoryInterface $passkeys)
    {
    }

    public function __invoke(#[CurrentUser] SecurityAdmin $admin): Response
    {
        return $this->render('admin/passkeys.html.twig', [
            'passkeys' => $this->passkeys->getAllForAdmin($admin->getAdminUserId()),
        ]);
    }
}
