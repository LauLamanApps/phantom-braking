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
 * Right after reporting a car on which AEB is mandatory, asks the reporter to report it to the authority of the
 * country where the car is registered as well, with a ready-made text. Only the session that just filed the report gets here: reports are anonymous,
 * so that is the only proof the "I reported it" answer comes from the reporter.
 */
#[Route('/{_locale}/reports/{id}/report-to-authority', name: 'incident_authority_prompt', requirements: ['_locale' => '%app.locales%'], methods: ['GET', 'POST'])]
final class PromptAuthorityReportController extends AbstractController
{
    public const string SESSION_KEY = 'incident.authority_prompt';

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

        $authority = $incident->getAuthority();
        if (null === $authority) {
            return $show;
        }

        $form = $this->createForm(AuthorityPromptType::class, options: ['authority' => $authority]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $session->remove(self::SESSION_KEY);
            $this->addFlash('success', 'report.thanks');

            $reported = $form->get('reported');
            if ($reported instanceof SubmitButton && $reported->isClicked()) {
                $this->commandBus->dispatch(new ConfirmReportedToAuthorityCommand($id));
                $this->addFlash('success', $authority->translationKey().'.thanks');
            }

            return $show;
        }

        // The reporter picks which of the authority's languages the text to copy is in.
        $locales = $authority->textLocales();
        $default = \in_array($request->getLocale(), $locales, true) ? $request->getLocale() : (\in_array('en', $locales, true) ? 'en' : $locales[0]);

        return $this->render('incident/authority_prompt.html.twig', [
            'incident' => $incident,
            'authority' => $authority,
            'form' => $form,
            'text_locales' => $locales,
            'text_locale' => \in_array($text, $locales, true) ? $text : $default,
        ]);
    }
}
