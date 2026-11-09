<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller;

use App\Incident\Domain\Command\RequestMediaDeletionCommand;
use App\Incident\Domain\Exception\PlateMismatchException;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Presentation\Form\WithdrawMediaType;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use App\SharedKernel\Domain\Repository\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The reporter asks to delete their footage and photos, proving it's their report with the plate
 * (never stored). An admin then approves or denies the request.
 */
#[Route('/{_locale}/reports/{id}/request-media-deletion', name: 'incident_request_media_deletion', requirements: ['_locale' => '%app.locales%'], methods: ['POST'])]
final class RequestMediaDeletionController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly RateLimiterFactoryInterface $mediaWithdrawalLimiter,
    ) {
    }

    public function __invoke(Request $request, IncidentId $id): RedirectResponse
    {
        // Back to the top of the report page, where the flash message is shown.
        $back = $this->redirectToRoute('incident_show', ['id' => (string) $id]);

        $form = $this->createForm(WithdrawMediaType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'withdraw.invalid');

            return $back;
        }

        // Plates are guessable; keep the number of attempts low.
        if (!$this->mediaWithdrawalLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            $this->addFlash('error', 'withdraw.rate_limited');

            return $back;
        }

        try {
            $this->commandBus->dispatch(new RequestMediaDeletionCommand($id, (string) $form->get('licensePlate')->getData()));
        } catch (HandlerFailedException $e) {
            $cause = $e->getPrevious();
            if ($cause instanceof NoResultException) {
                throw $this->createNotFoundException(previous: $cause);
            }
            if (!$cause instanceof PlateMismatchException) {
                throw $e;
            }
            $this->addFlash('error', 'withdraw.mismatch');

            return $back;
        }

        $this->addFlash('success', 'withdraw.requested');

        return $back;
    }
}
