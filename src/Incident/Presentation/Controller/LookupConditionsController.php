<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller;

use App\Incident\Domain\Exception\WeatherHistoryUnavailableException;
use App\Incident\Domain\Model\MeasuredWeather;
use App\Incident\Domain\Model\SunPosition;
use App\Incident\Domain\Service\WeatherHistoryInterface;
use App\Incident\Domain\ValueObject\IncidentCountry;
use App\Incident\Presentation\Request\ConditionsLookupRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Suggests weather and light for the report form from the measured weather and the sun at that place and time. */
#[Route('/api/conditions-lookup', name: 'conditions_lookup', methods: ['POST'])]
final class LookupConditionsController extends AbstractController
{
    public function __construct(
        private readonly WeatherHistoryInterface $weatherHistory,
        private readonly RateLimiterFactoryInterface $conditionsLookupLimiter,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] ConditionsLookupRequest $lookup): JsonResponse
    {
        if (!$this->conditionsLookupLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        $instant = new \DateTimeImmutable($lookup->occurredAt, IncidentCountry::fromString($lookup->country)->timezone());
        $sun = SunPosition::at($lookup->latitude, $lookup->longitude, $instant);

        try {
            $weather = $instant > new \DateTimeImmutable() ? null : $this->weatherHistory->at($lookup->latitude, $lookup->longitude, $instant);
        } catch (WeatherHistoryUnavailableException) {
            $weather = null; // the sun alone still helps
        }
        $measured = null !== $weather && $weather->isAvailable() ? $weather : null;

        $response = $this->json([
            'weather' => $measured?->suggestedWeather()?->value,
            'light' => $sun->suggestedLight($measured)?->value,
            'sun' => ['elevation' => $sun->getElevation(), 'azimuth' => $sun->getAzimuth()],
            'measured' => null === $measured ? null : self::measured($measured),
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /** @return array<string, int|float|null> */
    private static function measured(MeasuredWeather $weather): array
    {
        return [
            'temperature' => $weather->getTemperature(),
            'precipitation' => $weather->getPrecipitation(),
            'snowfall' => $weather->getSnowfall(),
            'cloudCover' => $weather->getCloudCover(),
            'visibility' => $weather->getVisibility(),
            'windGusts' => $weather->getWindGusts(),
        ];
    }
}
