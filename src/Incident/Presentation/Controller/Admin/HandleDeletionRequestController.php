<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller\Admin;

use App\Incident\Domain\Command\ApproveMediaDeletionCommand;
use App\Incident\Domain\Command\DenyMediaDeletionCommand;
use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/** Approve (delete all footage and photo files) or deny a reporter's deletion request. */
#[Route('/admin/reports/{id}/deletion/{decision}', name: 'admin_handle_deletion', requirements: ['decision' => 'approve|deny'], methods: ['POST'])]
final class HandleDeletionRequestController extends AbstractController
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    #[IsCsrfTokenValid('admin', tokenKey: '_token')]
    public function __invoke(IncidentId $id, string $decision): RedirectResponse
    {
        try {
            $this->commandBus->dispatch('approve' === $decision ? new ApproveMediaDeletionCommand($id) : new DenyMediaDeletionCommand($id));
            $this->addFlash('success', 'admin.flash.deletion_'.$decision.'d');
        } catch (HandlerFailedException $e) {
            $this->addFlash('error', $e->getPrevious()?->getMessage() ?? $e->getMessage());
        }

        return $this->redirectToRoute('admin_dashboard');
    }
}
