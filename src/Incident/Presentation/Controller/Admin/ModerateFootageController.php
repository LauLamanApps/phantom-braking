<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller\Admin;

use App\Incident\Domain\Command\ModerateFootageCommand;
use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/admin/reports/{id}/footage/{decision}', name: 'admin_moderate_footage', requirements: ['decision' => 'approve|reject'], methods: ['POST'])]
final class ModerateFootageController extends AbstractController
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    #[IsCsrfTokenValid('admin', tokenKey: '_token')]
    public function __invoke(IncidentId $id, string $decision): RedirectResponse
    {
        try {
            $this->commandBus->dispatch(new ModerateFootageCommand($id, 'approve' === $decision));
            $this->addFlash('success', 'admin.flash.footage_'.$decision.'d');
        } catch (HandlerFailedException $e) {
            $this->addFlash('error', $e->getPrevious()?->getMessage() ?? $e->getMessage());
        }

        return $this->redirectToRoute('admin_review', ['id' => (string) $id]);
    }
}
