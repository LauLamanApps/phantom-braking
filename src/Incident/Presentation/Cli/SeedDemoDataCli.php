<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Cli;

use App\Incident\Domain\Model\DashcamFootage;
use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\Model\Location;
use App\Incident\Domain\Model\MeasuredWeather;
use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\Service\ImageSanitizerInterface;
use App\Incident\Domain\Service\MediaStorageInterface;
use App\Incident\Domain\Service\PhotoPublisher;
use App\Incident\Domain\ValueObject\ActiveAssistSystem;
use App\Incident\Domain\ValueObject\BrakeSeverity;
use App\Incident\Domain\ValueObject\Consequence;
use App\Incident\Domain\ValueObject\DealerResponse;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\LightCondition;
use App\Incident\Domain\ValueObject\PhotoId;
use App\Incident\Domain\ValueObject\PlateMask;
use App\Incident\Domain\ValueObject\Recurrence;
use App\Incident\Domain\ValueObject\RoadSituation;
use App\Incident\Domain\ValueObject\RoadType;
use App\Incident\Domain\ValueObject\SuspectedTrigger;
use App\Incident\Domain\ValueObject\Weather;
use App\Vehicle\Domain\Model\VehicleSnapshot;
use App\Vehicle\Domain\ValueObject\Country;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Fills a dev database with random, clearly fake reports to preview the dashboard. Never runs in prod. */
#[AsCommand(name: 'app:demo:seed', description: 'DEV ONLY: insert random demo reports to preview the statistics.')]
final readonly class SeedDemoDataCli
{
    /** Per country: [make, model, first registration] — a plausible local fleet. */
    private const array VEHICLES = [
        'NL' => [
            ['TESLA', 'MODEL Y', '2023-05-01'], ['TESLA', 'MODEL 3', '2024-09-01'], ['KIA', 'NIRO', '2023-02-01'],
            ['VOLKSWAGEN', 'ID.3', '2022-03-01'], ['HYUNDAI', 'IONIQ 5', '2024-11-01'], ['MAZDA', 'CX-60', '2024-08-01'],
            ['TOYOTA', 'COROLLA', '2021-06-01'], ['FORD', 'PUMA', '2023-02-01'], ['RENAULT', 'MEGANE E-TECH', '2025-01-01'],
            ['VOLVO', 'EX30', '2025-03-01'], ['SKODA', 'ENYAQ', '2024-10-01'], ['PEUGEOT', '2008', '2025-04-01'],
        ],
        'DE' => [
            ['VOLKSWAGEN', 'GOLF', '2022-04-01'], ['VOLKSWAGEN', 'ID.4', '2023-06-01'], ['BMW', 'IX1', '2024-09-01'],
            ['MERCEDES-BENZ', 'GLC', '2023-11-01'], ['AUDI', 'Q4 E-TRON', '2024-02-01'], ['TESLA', 'MODEL Y', '2023-03-01'],
            ['SKODA', 'OCTAVIA', '2021-08-01'], ['OPEL', 'ASTRA', '2024-07-01'], ['CUPRA', 'BORN', '2025-02-01'],
        ],
        'FR' => [
            ['PEUGEOT', '3008', '2024-10-01'], ['PEUGEOT', '208', '2023-05-01'], ['RENAULT', 'CLIO', '2022-09-01'],
            ['RENAULT', 'SCENIC E-TECH', '2025-01-01'], ['CITROEN', 'C4', '2024-08-01'], ['TESLA', 'MODEL 3', '2024-03-01'],
            ['DACIA', 'SANDERO', '2023-01-01'], ['TOYOTA', 'YARIS CROSS', '2024-11-01'],
        ],
    ];

    /** Per country: [road, type, lat, lng] — known motorway stretches and a few other roads. */
    private const array SPOTS = [
        'NL' => [
            ['A15', RoadType::Motorway, 51.8930, 5.8565], ['A2', RoadType::Motorway, 52.0907, 5.0390], ['A12', RoadType::Motorway, 52.0612, 4.4230],
            ['A4', RoadType::Motorway, 52.2890, 4.7180], ['A28', RoadType::Motorway, 52.4970, 6.0820], ['N325', RoadType::ProvincialRoad, 51.9620, 5.9210],
            ['A1', RoadType::Motorway, 52.2390, 6.0210], ['A10', RoadType::Motorway, 52.3880, 4.8550], [null, RoadType::UrbanRoad, 52.3700, 4.8900],
        ],
        'DE' => [
            ['A3', RoadType::Motorway, 50.5640, 8.1720], ['A7', RoadType::Motorway, 51.6450, 9.9310], ['A9', RoadType::Motorway, 49.8900, 11.5800],
            ['A8', RoadType::Motorway, 48.4200, 9.9800], ['A2', RoadType::Motorway, 52.3300, 10.5200], ['A1', RoadType::Motorway, 53.4500, 10.1500],
            ['A5', RoadType::Motorway, 49.0300, 8.4000], ['B10', RoadType::ProvincialRoad, 48.7600, 9.2700], [null, RoadType::UrbanRoad, 52.5200, 13.4050],
        ],
        'FR' => [
            ['A6', RoadType::Motorway, 47.3300, 4.2500], ['A7', RoadType::Motorway, 45.4300, 4.8700], ['A10', RoadType::Motorway, 47.4000, 0.6800],
            ['A1', RoadType::Motorway, 49.7000, 2.7500], ['A13', RoadType::Motorway, 49.2400, 1.3000], ['A9', RoadType::Motorway, 43.6100, 3.8800],
            ['N7', RoadType::ProvincialRoad, 43.5500, 6.9000], [null, RoadType::UrbanRoad, 48.8566, 2.3522],
        ],
    ];

    /**
     * Incident countries without curated SPOTS: [lat, lng, jitter in degrees] around a few cities,
     * so the points land in the right country (tiny jitter for the micro-states).
     */
    private const array CITIES = [
        'AT' => [[48.21, 16.37, 0.1], [48.31, 14.29, 0.1], [47.07, 15.44, 0.1]],
        'BE' => [[50.85, 4.35, 0.1], [51.22, 4.40, 0.08], [50.63, 5.57, 0.1], [51.05, 3.72, 0.1]],
        'BG' => [[42.70, 23.32, 0.1], [42.14, 24.75, 0.1]],
        'HR' => [[45.81, 15.98, 0.1], [43.52, 16.47, 0.03]],
        'CY' => [[35.17, 33.36, 0.08], [34.70, 33.02, 0.03]],
        'CZ' => [[50.08, 14.44, 0.1], [49.20, 16.61, 0.1]],
        'DK' => [[55.68, 12.50, 0.05], [56.16, 10.15, 0.05], [55.40, 10.39, 0.05]],
        'EE' => [[59.42, 24.75, 0.04], [58.38, 26.72, 0.1]],
        'FI' => [[60.22, 24.94, 0.05], [61.50, 23.76, 0.1]],
        'GR' => [[38.00, 23.73, 0.05], [40.64, 22.96, 0.03]],
        'HU' => [[47.50, 19.04, 0.1], [47.53, 21.63, 0.1]],
        'IE' => [[53.35, -6.30, 0.05], [51.90, -8.47, 0.05]],
        'IT' => [[45.46, 9.19, 0.1], [41.90, 12.50, 0.1], [44.49, 11.34, 0.1]],
        'LV' => [[56.95, 24.15, 0.05]],
        'LT' => [[54.69, 25.28, 0.1], [54.90, 23.90, 0.1]],
        'LU' => [[49.61, 6.13, 0.05]],
        'MT' => [[35.89, 14.45, 0.01]],
        'PL' => [[52.23, 21.01, 0.1], [50.06, 19.94, 0.1], [52.41, 16.93, 0.1]],
        'PT' => [[38.76, -9.12, 0.03], [41.16, -8.59, 0.03]],
        'RO' => [[44.43, 26.10, 0.1], [46.77, 23.59, 0.1]],
        'SK' => [[48.16, 17.13, 0.04], [48.72, 21.26, 0.1]],
        'SI' => [[46.06, 14.51, 0.08]],
        'ES' => [[40.42, -3.70, 0.1], [41.40, 2.12, 0.03], [39.47, -0.40, 0.03], [37.39, -5.98, 0.1]],
        'SE' => [[59.33, 18.00, 0.05], [57.71, 12.00, 0.04]],
        'IS' => [[64.12, -21.85, 0.03]],
        'LI' => [[47.14, 9.52, 0.01]],
        'NO' => [[59.93, 10.75, 0.04], [60.39, 5.34, 0.02]],
        'CH' => [[47.38, 8.54, 0.05], [46.95, 7.45, 0.05], [46.21, 6.14, 0.02]],
        'GB' => [[51.51, -0.13, 0.1], [53.48, -2.24, 0.1], [52.49, -1.89, 0.1]],
        'AD' => [[42.51, 1.52, 0.01]],
        'MC' => [[43.74, 7.42, 0.003]],
        'SM' => [[43.94, 12.45, 0.01]],
        'VA' => [[41.903, 12.453, 0.001]],
        'AL' => [[41.33, 19.82, 0.05]],
        'BA' => [[43.86, 18.41, 0.05]],
        'ME' => [[42.44, 19.26, 0.04]],
        'MK' => [[42.00, 21.43, 0.05]],
        'RS' => [[44.79, 20.45, 0.08], [45.27, 19.83, 0.05]],
        'MD' => [[47.01, 28.86, 0.08]],
        'UA' => [[50.45, 30.52, 0.1], [49.84, 24.03, 0.1]],
    ];

    /** Where demoPhoto() draws the license plate, as a mask (fractions of 1600×1200). */
    private const array DEMO_PLATE = [0.445, 0.72, 0.11, 0.045];

    public function __construct(
        private IncidentRepositoryInterface $incidents,
        private MediaStorageInterface $mediaStorage,
        private ImageSanitizerInterface $imageSanitizer,
        private PhotoPublisher $photoPublisher,
        #[Autowire('%kernel.environment%')]
        private string $environment,
        #[Autowire('%kernel.project_dir%/.dev/demo-media/dashcam-demo.mp4')]
        private string $demoVideo,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option] int $count = 150,
        #[Option('Where the incidents happened: any European country code, e.g. NL, BE, IT')] string $country = 'NL',
        #[Option('How many of the reports get footage and/or photos')] int $withMedia = 0,
    ): int {
        if ('dev' !== $this->environment) {
            $io->error('Demo data can only be seeded in the dev environment.');

            return Command::FAILURE;
        }

        $target = IncidentCountry::tryFromString($country);
        if (null === $target) {
            $io->error(sprintf('Unknown country "%s". Use a European country code: %s.', $country, implode(', ', IncidentCountry::EUROPE)));

            return Command::INVALID;
        }
        // Cars are registered where we can look them up (NL, DE, FR): at home there, visiting anywhere else.
        $home = Country::tryFrom($target->code);
        $registrations = null !== $home ? [$home] : Country::cases();
        $spots = self::SPOTS[$target->code] ?? array_map(
            static fn (array $city): array => [null, null, ...$city],
            self::CITIES[$target->code],
        );

        $pick = static fn (array $items): mixed => $items[array_rand($items)];
        $weighted = static function (array $weights): mixed {
            $r = random_int(1, array_sum(array_column($weights, 1)));
            foreach ($weights as [$value, $weight]) {
                if (($r -= $weight) <= 0) {
                    return $value;
                }
            }

            return $weights[0][0];
        };

        if ($withMedia > 0 && !is_file($this->demoVideo)) {
            $io->error('Demo video missing: '.$this->demoVideo);

            return Command::FAILURE;
        }
        $mediaStats = ['footage' => 0, 'photos' => 0];

        for ($i = 0; $i < $count; ++$i) {
            $registration = $pick($registrations);
            [$make, $model, $admitted] = $pick(self::VEHICLES[$registration->value]);
            // Curated spots: a known road, a few km around it. Cities: no road number, a random road type.
            [$road, $type, $lat, $lng, $jitter] = [...$pick($spots), 0.015];
            $type ??= $weighted([[RoadType::Motorway, 50], [RoadType::ProvincialRoad, 25], [RoadType::UrbanRoad, 25]]);
            $spread = (int) round($jitter * 10000);
            $before = RoadType::Motorway === $type ? random_int(90, 130) : random_int(30, 80);

            $incident = new Incident(
                id: IncidentId::generate(),
                country: $registration,
                plateFingerprint: hash('sha256', 'demo'.$registration->value.random_int(1, (int) ($count * 0.7))),
                vehicle: new VehicleSnapshot($make, $model, new \DateTimeImmutable($admitted)),
                occurredAt: new \DateTimeImmutable(sprintf('-%d minutes', random_int(60, 60 * 24 * 330))),
                location: new Location($target, $type, $weighted([
                    [RoadSituation::UnderViaduct, 30], [RoadSituation::StraightRoad, 20], [RoadSituation::SignGantry, 15],
                    [RoadSituation::Bridge, 10], [RoadSituation::TunnelEntrance, 8], [RoadSituation::Curve, 8], [RoadSituation::OnRamp, 6],
                ]), $road, $lat + random_int(-$spread, $spread) / 10000, $lng + random_int(-$spread, $spread) / 10000),
                severity: $weighted([[BrakeSeverity::Strong, 40], [BrakeSeverity::Emergency, 30], [BrakeSeverity::Light, 20], [BrakeSeverity::WarningOnly, 10]]),
                speedBefore: $before,
                speedAfter: random_int(0, 3) ? random_int((int) ($before * 0.3), (int) ($before * 0.8)) : null,
                trigger: $weighted([[SuspectedTrigger::ViaductOrGantry, 30], [SuspectedTrigger::NothingVisible, 25], [SuspectedTrigger::Shadow, 15], [SuspectedTrigger::TruckAdjacentLane, 12], [SuspectedTrigger::OncomingTraffic, 8], [SuspectedTrigger::LowSunOrReflection, 10]]),
                assistSystem: $weighted([[ActiveAssistSystem::AdaptiveCruise, 40], [ActiveAssistSystem::AdaptiveCruiseWithLaneCentering, 30], [ActiveAssistSystem::None, 25], [ActiveAssistSystem::Unknown, 5], [ActiveAssistSystem::TeslaAssistedDriving, 'TESLA' === mb_strtoupper($make) ? 30 : 0]]),
                warningShown: $pick([true, false, null]),
                weather: $weather = $weighted([[Weather::Dry, 70], [Weather::Rain, 25], [Weather::Fog, 5]]),
                light: $weighted([[LightCondition::Daylight, 55], [LightCondition::LowSun, 20], [LightCondition::Twilight, 10], [LightCondition::DarkLit, 15]]),
                consequence: $weighted([[Consequence::DriverStartled, 40], [Consequence::FollowerHadToBrake, 30], [Consequence::NearRearEnd, 20], [Consequence::RearEndCollision, 7], [Consequence::None, 3]]),
                recurrence: $weighted([[Recurrence::FirstTime, 35], [Recurrence::Few, 40], [Recurrence::Regular, 20], [Recurrence::Frequent, 5]]),
                dealerResponse: $weighted([[DealerResponse::NotContacted, 30], [DealerResponse::NoFaultFound, 30], [DealerResponse::BlamedDriver, 15], [DealerResponse::SoftwareUpdate, 15], [DealerResponse::Acknowledged, 10]]),
                reportedToAuthority: 0 === random_int(0, 12),
                windscreenReplacedRecently: $pick([false, false, true, null]),
                odometerKm: random_int(0, 1) ? random_int(1000, 90000) : null,
                softwareVersion: random_int(0, 2) ? null : sprintf('%d.%d.%d', random_int(2024, 2026), random_int(1, 12), random_int(0, 5)),
                description: '[DEMO] Generated demo report.',
                reportedAt: new \DateTimeImmutable(),
            );

            // Plausible measured weather that mostly agrees with what the "reporter" said (no Open-Meteo calls for demo data).
            $incident->recordMeasuredWeather(MeasuredWeather::measured(
                checkedAt: new \DateTimeImmutable(),
                temperature: random_int(-80, 280) / 10,
                precipitation: Weather::Rain === $weather ? random_int(1, 60) / 10 : 0.0,
                snowfall: 0.0,
                cloudCover: Weather::Dry === $weather ? random_int(0, 100) : random_int(70, 100),
                weatherCode: match ($weather) {
                    Weather::Rain => 61, Weather::Fog => 45, default => 2
                },
                visibility: Weather::Fog === $weather ? random_int(50, 900) : random_int(8000, 40000),
                windSpeed: random_int(0, 400) / 10,
                windGusts: random_int(50, 700) / 10,
                humidity: random_int(40, 100),
                solarRadiation: (float) random_int(0, 700),
            ));

            if ($i < $withMedia) {
                $this->addDemoMedia($incident, $make, $model, $road, $mediaStats);
            }
            $this->incidents->persist($incident);
        }
        $this->incidents->flush();

        $io->success(sprintf('Inserted %d demo reports in %s. Run "make db-setup" to start clean.', $count, $target->code));
        if ($withMedia > 0) {
            $io->note(sprintf('%d of them got media: %d videos and %d photos, in a realistic moderation mix (mostly approved).', min($withMedia, $count), $mediaStats['footage'], $mediaStats['photos']));
        }

        return Command::SUCCESS;
    }

    /**
     * Footage and/or photos through the real storage and sanitiser, in a moderation mix:
     * mostly approved, some pending or rejected, and some with a deletion request from the reporter.
     *
     * @param array{footage: int, photos: int} $stats
     */
    private function addDemoMedia(Incident $incident, string $make, string $model, ?string $road, array &$stats): void
    {
        $withFootage = 0 !== random_int(0, 2);
        $photoCount = $withFootage && 0 === random_int(0, 2) ? 0 : random_int(1, 4);

        if ($withFootage) {
            $copy = (string) tempnam(sys_get_temp_dir(), 'demo_video_');
            copy($this->demoVideo, $copy);
            $incident->attachFootage(new DashcamFootage(
                path: $this->mediaStorage->store($incident->getId(), 'footage.mp4', $copy, 'video/mp4'),
                mimeType: 'video/mp4',
                size: (int) filesize($this->demoVideo),
                publicationConsent: true,
            ));
            ++$stats['footage'];
        }

        for ($n = 1; $n <= $photoCount; ++$n) {
            $image = $this->imageSanitizer->sanitize($this->demoPhoto($make, $model, $road, $n));
            $photoId = PhotoId::generate();
            $incident->addPhoto(
                $photoId,
                $this->mediaStorage->store($incident->getId(), sprintf('photo-%s.%s', $photoId, $image->extension), $image->path, $image->mimeType),
                $image->mimeType, $image->size, $image->width, $image->height, true,
            );
            // Most demo reporters black out the plate; some forget, so moderators have something to fix.
            $masks = 0 === random_int(0, 3) ? [] : [PlateMask::fromArray(self::DEMO_PLATE)];
            $this->photoPublisher->publishMasked($incident, $photoId, $masks);
            ++$stats['photos'];
        }

        $roll = random_int(1, 100);
        if ($roll <= 65) {
            self::approveAll($incident);
        } elseif ($roll > 93) {
            self::approveAll($incident);
            $incident->requestMediaDeletion(new \DateTimeImmutable());
        } elseif ($roll > 85) {
            self::rejectAll($incident);
        } // else: still pending
    }

    private static function approveAll(Incident $incident): void
    {
        if ($incident->getFootage()->exists()) {
            $incident->approveFootage();
        }
        foreach ($incident->getPhotos() as $photo) {
            // A moderator would mask a forgotten plate before approving; leave those pending.
            if ([] !== $photo->getMasks()) {
                $incident->approvePhoto($photo->getId());
            }
        }
    }

    private static function rejectAll(Incident $incident): void
    {
        if ($incident->getFootage()->exists()) {
            $incident->rejectFootage();
        }
        foreach ($incident->getPhotos() as $photo) {
            $incident->rejectPhoto($photo->getId());
        }
    }

    /** A recognisably fake "aftermath" photo: road, car, damage mark and a DEMO label. Returns a temp file. */
    private function demoPhoto(string $make, string $model, ?string $road, int $n): string
    {
        $w = 1600;
        $h = 1200;
        $img = imagecreatetruecolor($w, $h);
        $colour = static fn (int $r, int $g, int $b): int => (int) imagecolorallocate($img, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));

        // Sky gradient (lighter or dusk), road, lane markings.
        $dusk = 0 === $n % 2;
        for ($y = 0; $y < 560; ++$y) {
            $t = $y / 560;
            imageline($img, 0, $y, $w, $y, $dusk
                ? $colour((int) (250 - 80 * $t), (int) (170 - 60 * $t), (int) (120 + 40 * $t))
                : $colour((int) (120 + 90 * $t), (int) (170 + 60 * $t), (int) (230 + 20 * $t)));
        }
        imagefilledrectangle($img, 0, 560, $w, $h, $colour(70, 72, 76));
        imagefilledpolygon($img, [700, 560, 900, 560, 1400, $h, 200, $h], $colour(88, 90, 95));
        for ($y = 600; $y < $h; $y += 90) {
            imagefilledrectangle($img, 790 - intdiv($y - 560, 12), $y, 810 + intdiv($y - 560, 12), $y + 40, $colour(235, 235, 230));
        }

        // The car (seen from behind) with a dented, red-marked bumper.
        $body = [$colour(40, 70, 140), $colour(150, 150, 155), $colour(30, 30, 32), $colour(180, 30, 30)][$n % 4];
        imagefilledrectangle($img, 560, 640, 1040, 980, $body);
        imagefilledrectangle($img, 620, 520, 980, 660, $body);
        imagefilledrectangle($img, 650, 545, 950, 640, $colour(60, 80, 100));
        imagefilledrectangle($img, 590, 760, 690, 810, $colour(200, 30, 30));
        imagefilledrectangle($img, 910, 760, 1010, 810, $colour(200, 30, 30));
        // A (fake) yellow Dutch plate on the bumper, at DEMO_PLATE.
        imagefilledrectangle($img, 720, 868, 880, 912, $colour(245, 200, 0));
        imagefilledrectangle($img, 720, 868, 738, 912, $colour(20, 60, 160));
        $plate = imagecreatetruecolor(72, 14);
        imagefill($plate, 0, 0, (int) imagecolorallocate($plate, 245, 200, 0));
        imagestring($plate, 3, 4, 0, 'XX-99-XX', (int) imagecolorallocate($plate, 10, 10, 10));
        imagecopyresized($img, $plate, 742, 872, 0, 0, 136, 36, 72, 14);
        imagefilledellipse($img, 960, 950, 140, 80, $colour(230, 60, 40));
        imagesetthickness($img, 6);
        imageline($img, 830, 920, 970, 990, $colour(20, 20, 20));
        imageline($img, 850, 990, 960, 915, $colour(20, 20, 20));

        // DEMO label, drawn small and scaled up (GD's built-in font is tiny).
        $label = sprintf('DEMO %s %s %s #%d', $make, $model, $road ?? '', $n);
        $small = imagecreatetruecolor(8 * \strlen($label) + 16, 28);
        imagefill($small, 0, 0, (int) imagecolorallocate($small, 20, 20, 20));
        imagestring($small, 5, 8, 6, $label, (int) imagecolorallocate($small, 255, 255, 255));
        imagecopyresized($img, $small, 40, 40, 0, 0, imagesx($small) * 3, imagesy($small) * 3, imagesx($small), imagesy($small));

        $path = (string) tempnam(sys_get_temp_dir(), 'demo_photo_');
        imagejpeg($img, $path, 88);

        return $path;
    }
}
