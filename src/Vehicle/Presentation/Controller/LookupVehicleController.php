<?php

declare(strict_types=1);

namespace App\Vehicle\Presentation\Controller;

use App\Vehicle\Domain\Exception\InvalidLicensePlateException;
use App\Vehicle\Domain\Exception\UnsupportedCountryException;
use App\Vehicle\Domain\Exception\VehicleNotFoundException;
use App\Vehicle\Domain\Exception\VehicleRegistryUnavailableException;
use App\Vehicle\Domain\Service\VehicleRegistryInterface;
use App\Vehicle\Domain\ValueObject\LicensePlate;
use App\Vehicle\Presentation\Request\VehicleLookupRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Live preview of the vehicle while the reporter is typing the plate. */
#[Route('/api/vehicles/lookup', name: 'vehicle_lookup', methods: ['POST'])]
final class LookupVehicleController extends AbstractController
{
    public function __construct(
        private readonly VehicleRegistryInterface $registry,
        private readonly RateLimiterFactoryInterface $vehicleLookupLimiter,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] VehicleLookupRequest $lookup): JsonResponse
    {
        if (!$this->vehicleLookupLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        try {
            $vehicle = $this->registry->lookup($lookup->country, LicensePlate::fromInput($lookup->plate));
        } catch (InvalidLicensePlateException) {
            return $this->json(['error' => 'invalid_plate'], 400);
        } catch (UnsupportedCountryException) {
            return $this->json(['error' => 'unsupported_country'], 400);
        } catch (VehicleNotFoundException) {
            return $this->json(['error' => 'not_found'], 404);
        } catch (VehicleRegistryUnavailableException) {
            return $this->json(['error' => 'registry_unavailable'], 503);
        }

        $response = $this->json($vehicle->toArray());
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
