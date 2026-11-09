<?php

declare(strict_types=1);

namespace App\Admin\Presentation\Controller;

use App\Admin\Domain\Command\RemovePasskeyCommand;
use App\Admin\Domain\Exception\LastPasskeyException;
use App\Admin\Domain\ValueObject\AdminPasskeyId;
use App\Admin\Infrastructure\Security\SecurityAdmin;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/admin/passkeys/{id}/remove', name: 'admin_passkey_remove', methods: ['POST'])]
final class RemovePasskeyController extends AbstractController
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    #[IsCsrfTokenValid('admin', tokenKey: '_token')]
    public function __invoke(AdminPasskeyId $id, #[CurrentUser] SecurityAdmin $admin): RedirectResponse
    {
        try {
            $this->commandBus->dispatch(new RemovePasskeyCommand($admin->getAdminUserId(), $id));
            $this->addFlash('success', 'admin.passkeys.removed');
        } catch (HandlerFailedException $e) {
            if (!$e->getPrevious() instanceof LastPasskeyException) {
                throw new NotFoundHttpException(previous: $e);
            }
            $this->addFlash('error', 'admin.passkeys.last');
        }

        return $this->redirectToRoute('admin_passkeys');
    }
}
