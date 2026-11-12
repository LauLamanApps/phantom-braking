<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller;

use App\Incident\Domain\Command\FootageUpload;
use App\Incident\Domain\Command\PhotoUpload;
use App\Incident\Domain\Command\ReportIncidentCommand;
use App\Incident\Domain\Exception\AssistSystemNotAvailableException;
use App\Incident\Domain\Exception\InvalidImageException;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Presentation\Form\ReportIncidentData;
use App\Incident\Presentation\Form\ReportIncidentType;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use App\Vehicle\Domain\Exception\InvalidLicensePlateException;
use App\Vehicle\Domain\Exception\UnsupportedCountryException;
use App\Vehicle\Domain\Exception\VehicleNotFoundException;
use App\Vehicle\Domain\Exception\VehicleRegistryUnavailableException;
use App\Vehicle\Domain\Service\VehicleRegistryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/report', name: 'incident_report', requirements: ['_locale' => '%app.locales%'], methods: ['GET', 'POST'])]
final class ReportIncidentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly IncidentRepositoryInterface $incidents,
        private readonly RateLimiterFactoryInterface $incidentReportLimiter,
        private readonly TranslatorInterface $translator,
        private readonly VehicleRegistryInterface $vehicleRegistry,
        #[Autowire('%kernel.project_dir%/var/storage/staging')]
        private readonly string $stagingDirectory,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $countries = $this->vehicleRegistry->supportedCountries();
        $data = new ReportIncidentData();
        $data->country = $countries[0] ?? null;
        $data->incidentCountry = $data->country?->value;
        $form = $this->createForm(ReportIncidentType::class, $data, ['countries' => $countries, 'locale' => $request->getLocale()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->incidentReportLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $form->addError(new FormError($this->translator->trans('report.rate_limited')));

                return $this->render('incident/report.html.twig', ['form' => $form], new Response(status: 429));
            }

            $id = IncidentId::generate();

            $command = $this->toCommand($id, $data);

            try {
                $this->commandBus->dispatch($command);

                if ($this->incidents->get($id)->shouldBeReportedToAuthority()) {
                    $request->getSession()->set(PromptAuthorityReportController::SESSION_KEY, (string) $id);

                    return $this->redirectToRoute('incident_authority_prompt', ['id' => (string) $id]);
                }

                $this->addFlash('success', 'report.thanks');

                return $this->redirectToRoute('incident_show', ['id' => (string) $id]);
            } catch (HandlerFailedException $e) {
                $staged = [$command->footage?->temporaryPath, ...array_map(static fn (PhotoUpload $p): string => $p->temporaryPath, $command->photos)];
                foreach (array_filter($staged) as $path) {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
                $this->translateFailure($form, $e);
            }
        }

        return $this->render('incident/report.html.twig', ['form' => $form]);
    }

    /**
     * Uploading is the consent to publish after review: the form says so right next to the upload fields
     * (a clear affirmative action under GDPR art. 4(11)). Withdrawing it later is described in the privacy statement.
     */
    private function toCommand(IncidentId $id, ReportIncidentData $data): ReportIncidentCommand
    {
        $footage = null;
        if (null !== $data->footage) {
            $extension = $data->footage->guessExtension() ?? 'bin';
            $mimeType = (string) $data->footage->getMimeType();
            $size = (int) $data->footage->getSize();
            $staged = $data->footage->move($this->stagingDirectory, $id.'.'.$extension);

            $footage = new FootageUpload($staged->getPathname(), $extension, $mimeType, $size, publicationConsent: true);
        }

        $photos = [];
        $masks = $data->masksPerPhoto(); // validated by the form
        foreach ($data->photos as $i => $file) {
            $staged = $file->move($this->stagingDirectory, sprintf('%s-photo-%d.upload', $id, $i));
            $photos[] = new PhotoUpload($staged->getPathname(), publicationConsent: true, masks: $masks[$i] ?? []);
        }

        return new ReportIncidentCommand(
            incidentId: $id,
            country: self::validated($data->country),
            licensePlate: self::validated($data->licensePlate),
            occurredAt: self::validated($data->occurredAt),
            incidentCountry: IncidentCountry::fromString(self::validated($data->incidentCountry)),
            roadType: self::validated($data->roadType),
            roadSituation: self::validated($data->roadSituation),
            roadNumber: $data->roadNumber,
            latitude: $data->latitude,
            longitude: $data->longitude,
            direction: $data->direction,
            speedLimit: $data->speedLimit,
            severity: self::validated($data->severity),
            speedBefore: self::validated($data->speedBefore),
            speedAfter: $data->speedAfter,
            trigger: self::validated($data->trigger),
            assistSystem: self::validated($data->assistSystem),
            warningShown: $data->warningShown,
            weather: self::validated($data->weather),
            light: self::validated($data->light),
            consequence: self::validated($data->consequence),
            recurrence: self::validated($data->recurrence),
            dealerResponse: self::validated($data->dealerResponse),
            reportedToAuthority: $data->reportedToAuthority,
            windscreenReplacedRecently: $data->windscreenReplacedRecently,
            odometerKm: $data->odometerKm,
            softwareVersion: $data->softwareVersion,
            description: $data->description,
            footage: $footage,
            photos: $photos,
        );
    }

    /**
     * The form's NotNull/NotBlank constraints guarantee these are set once it is valid.
     *
     * @template T
     *
     * @param T|null $value
     *
     * @return T
     */
    private static function validated(mixed $value): mixed
    {
        return $value ?? throw new \LogicException('A required field passed validation without a value.');
    }

    /** @param FormInterface<ReportIncidentData> $form */
    private function translateFailure(FormInterface $form, HandlerFailedException $e): void
    {
        $cause = $e->getPrevious();

        match (true) {
            $cause instanceof VehicleNotFoundException => $form->get('licensePlate')->addError(new FormError($this->translator->trans('report.plate.not_found'))),
            $cause instanceof InvalidLicensePlateException => $form->get('licensePlate')->addError(new FormError($this->translator->trans('report.plate.invalid', domain: 'validators'))),
            $cause instanceof AssistSystemNotAvailableException => $form->get('assistSystem')->addError(new FormError($this->translator->trans('report.assist.not_available'))),
            $cause instanceof InvalidImageException => $form->get('photos')->addError(new FormError($this->translator->trans('report.photos.invalid'))),
            $cause instanceof UnsupportedCountryException => $form->get('country')->addError(new FormError($this->translator->trans('report.country.unsupported'))),
            $cause instanceof VehicleRegistryUnavailableException => $form->addError(new FormError($this->translator->trans('report.registry_unavailable'))),
            $cause instanceof \DomainException => $form->addError(new FormError($cause->getMessage())),
            default => throw $e,
        };
    }
}
