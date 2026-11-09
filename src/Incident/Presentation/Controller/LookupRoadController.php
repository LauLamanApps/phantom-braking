<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller;

use App\Incident\Domain\Exception\RoadLocatorUnavailableException;
use App\Incident\Domain\Service\RoadLocatorInterface;
use App\Incident\Presentation\Request\RoadLookupRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Suggests country, road number, road type and speed limit for a point picked on the map. */
#[Route('/api/road-lookup', name: 'road_lookup', methods: ['POST'])]
final class LookupRoadController extends AbstractController
{
    public function __construct(
        private readonly RoadLocatorInterface $roadLocator,
        private readonly RateLimiterFactoryInterface $roadLookupLimiter,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] RoadLookupRequest $lookup): JsonResponse
    {
        if (!$this->roadLookupLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        try {
            $road = $this->roadLocator->locate($lookup->latitude, $lookup->longitude);
        } catch (RoadLocatorUnavailableException) {
            return $this->json(['error' => 'unavailable'], 503);
        }

        $response = $this->json([
            'country' => $road->country?->code,
            'roadNumber' => $road->roadNumber,
            'roadType' => $road->roadType?->value,
            'roadSituation' => $road->situation?->value,
            'speedLimit' => $road->speedLimit,
            'name' => $road->name,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
