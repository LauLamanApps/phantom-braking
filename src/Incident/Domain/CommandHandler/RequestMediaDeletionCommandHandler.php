<?php

declare(strict_types=1);

namespace App\Incident\Domain\CommandHandler;

use App\Incident\Domain\Command\RequestMediaDeletionCommand;
use App\Incident\Domain\Exception\PlateMismatchException;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\SharedKernel\Domain\Messenger\Handler\CommandHandlerInterface;
use App\Vehicle\Domain\Exception\InvalidLicensePlateException;
use App\Vehicle\Domain\Service\PlateFingerprinterInterface;
use App\Vehicle\Domain\ValueObject\LicensePlate;

final readonly class RequestMediaDeletionCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private IncidentRepositoryInterface $incidents,
        private PlateFingerprinterInterface $fingerprinter,
    ) {
    }

    public function __invoke(RequestMediaDeletionCommand $command): void
    {
        $incident = $this->incidents->get($command->incidentId);

        try {
            $fingerprint = $this->fingerprinter->fingerprint($incident->getCountry(), LicensePlate::fromInput($command->licensePlate));
        } catch (InvalidLicensePlateException $e) {
            throw new PlateMismatchException('The plate does not match this report.', previous: $e);
        }

        if (!hash_equals($incident->getPlateFingerprint(), $fingerprint)) {
            throw new PlateMismatchException('The plate does not match this report.');
        }

        $incident->requestMediaDeletion(new \DateTimeImmutable());
        $this->incidents->flush();
    }
}
