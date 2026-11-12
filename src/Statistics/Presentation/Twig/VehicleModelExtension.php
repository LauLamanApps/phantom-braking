<?php

declare(strict_types=1);

namespace App\Statistics\Presentation\Twig;

use App\Statistics\Presentation\VehicleModelSlug;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** `vehicle_model_path('VOLKSWAGEN', 'ID.3')` → /en/vehicles/volkswagen/id-3, or null when the name has no URL-safe part. */
final class VehicleModelExtension extends AbstractExtension
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('vehicle_model_path', $this->path(...))];
    }

    public function path(string $make, string $model): ?string
    {
        $params = VehicleModelSlug::params($make, $model);

        return null === $params ? null : $this->urls->generate('vehicle_model', $params);
    }
}
