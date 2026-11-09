<?php

declare(strict_types=1);

namespace App\Vehicle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * Vehicle data as the national registry reported it at the moment of the
 * report. Stored as a snapshot so statistics stay stable even if the
 * registry changes (export, re-registration).
 *
 * The type-approval number and variant matter: they identify the technical
 * platform (and therefore the ADAS hardware) across brand names.
 */
#[ORM\Embeddable]
class VehicleSnapshot
{
    public function __construct(
        #[ORM\Column(length: 64)]
        private string $make,
        #[ORM\Column(length: 128)]
        private string $model,
        #[ORM\Column(type: 'date_immutable', nullable: true)]
        private ?\DateTimeImmutable $firstAdmission = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $vehicleKind = null,
        #[ORM\Column(length: 8, nullable: true)]
        private ?string $europeanCategory = null,
        #[ORM\Column(length: 128, nullable: true)]
        private ?string $fuel = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $hybridClass = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $typeApprovalNumber = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $variant = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $execution = null,
        #[ORM\Column(nullable: true)]
        private ?bool $openRecall = null,
    ) {
        $this->make = self::tidy($make);
        $this->model = self::tidy($model);
    }

    private static function tidy(string $value): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', $value)));
    }

    public function getMake(): string
    {
        return $this->make;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getFirstAdmission(): ?\DateTimeImmutable
    {
        return $this->firstAdmission;
    }

    public function getFirstAdmissionYear(): ?int
    {
        return null === $this->firstAdmission ? null : (int) $this->firstAdmission->format('Y');
    }

    public function getVehicleKind(): ?string
    {
        return $this->vehicleKind;
    }

    public function getEuropeanCategory(): ?string
    {
        return $this->europeanCategory;
    }

    public function getFuel(): ?string
    {
        return $this->fuel;
    }

    public function getHybridClass(): ?string
    {
        return $this->hybridClass;
    }

    public function getTypeApprovalNumber(): ?string
    {
        return $this->typeApprovalNumber;
    }

    public function getVariant(): ?string
    {
        return $this->variant;
    }

    public function getExecution(): ?string
    {
        return $this->execution;
    }

    public function hasOpenRecall(): ?bool
    {
        return $this->openRecall;
    }

    /** Since 7 July 2024 every newly registered car must have AEB (EU General Safety Regulation 2019/2144). */
    public function isSubjectToGsr2(): ?bool
    {
        if (null === $this->firstAdmission) {
            return null;
        }

        return $this->firstAdmission >= new \DateTimeImmutable('2024-07-07');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'make' => $this->make,
            'model' => $this->model,
            'firstAdmission' => $this->firstAdmission?->format('Y-m-d'),
            'firstAdmissionYear' => $this->getFirstAdmissionYear(),
            'vehicleKind' => $this->vehicleKind,
            'europeanCategory' => $this->europeanCategory,
            'fuel' => $this->fuel,
            'hybridClass' => $this->hybridClass,
            'typeApprovalNumber' => $this->typeApprovalNumber,
            'variant' => $this->variant,
            'openRecall' => $this->openRecall,
        ];
    }
}
