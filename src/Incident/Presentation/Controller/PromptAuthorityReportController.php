<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller;

use App\Incident\Domain\Command\ConfirmReportedToAuthorityCommand;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Presentation\Form\AuthorityPromptType;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use App\SharedKernel\Domain\Repository\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Right after reporting a car on which AEB is mandatory, asks the reporter to report it to the ILT as well,
 * with a ready-made text. Only the session that just filed the report gets here: reports are anonymous,
 * so that is the only proof the "I reported it" answer comes from the reporter.
 */
#[Route('/{_locale}/reports/{id}/report-to-ilt', name: 'incident_authority_prompt', requirements: ['_locale' => '%app.locales%'], methods: ['GET', 'POST'])]
final class PromptAuthorityReportController extends AbstractController
{
    public const string SESSION_KEY = 'incident.authority_prompt';

    /** The ILT reads Dutch and English; the reporter picks which one the text to copy is in. */
    private const array TEXT_LOCALES = ['nl', 'en'];

    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, IncidentId $id, #[MapQueryParameter] ?string $text = null): Response
    {
        $show = $this->redirectToRoute('incident_show', ['id' => (string) $id]);
        $session = $request->getSession();
        if ((string) $id !== $session->get(self::SESSION_KEY)) {
            return $show;
        }

        try {
            $incident = $this->incidents->get($id);
        } catch (NoResultException $e) {
            throw $this->createNotFoundException(previous: $e);
        }

        $form = $this->createForm(AuthorityPromptType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $session->remove(self::SESSION_KEY);
            $this->addFlash('success', 'report.thanks');

            $reported = $form->get('reported');
            if ($reported instanceof SubmitButton && $reported->isClicked()) {
                $this->commandBus->dispatch(new ConfirmReportedToAuthorityCommand($id));
                $this->addFlash('success', 'authority_prompt.thanks');
            }

            return $show;
        }

        $default = \in_array($request->getLocale(), self::TEXT_LOCALES, true) ? $request->getLocale() : 'en';

        return $this->render('incident/authority_prompt.html.twig', [
            'incident' => $incident,
            'form' => $form,
            'text_locales' => self::TEXT_LOCALES,
            'text_locale' => \in_array($text, self::TEXT_LOCALES, true) ? $text : $default,
        ]);
    }
}
