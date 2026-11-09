<?php

declare(strict_types=1);

namespace App\Statistics\Presentation\Twig;

use App\Vehicle\Domain\ValueObject\Country;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Colours a road number like the road sign of its country, so "A7" reads as
 * a blue Autobahn in Germany and a red autoroute in France.
 */
final class RoadSignExtension extends AbstractExtension
{
    private const string RED = 'bg-red-700 text-white';
    private const string BLUE = 'bg-blue-700 text-white';
    private const string YELLOW = 'bg-yellow-400 text-stone-900 inset-ring inset-ring-stone-900/40';
    private const string GREEN = 'bg-green-700 text-white';
    private const string NEUTRAL = 'bg-stone-200 text-stone-900 dark:bg-stone-700 dark:text-white';

    /** Road class prefix → sign colour, per country. E-roads (European routes) are green everywhere. */
    private const array SIGNS = [
        'NL' => ['A' => self::RED, 'N' => self::YELLOW],             // rijksweg A red, N-weg yellow
        'DE' => ['A' => self::BLUE, 'B' => self::YELLOW],            // Autobahn blue, Bundesstraße yellow
        'FR' => ['A' => self::RED, 'N' => self::RED, 'D' => self::YELLOW], // autoroute & route nationale red, départementale yellow
    ];

    public function getFunctions(): array
    {
        return [new TwigFunction('road_sign_class', $this->roadSignClass(...))];
    }

    public function roadSignClass(Country|string $country, string $road): string
    {
        $code = $country instanceof Country ? $country->value : strtoupper($country);
        $prefix = (string) preg_replace('/[^A-Z].*$/', '', strtoupper($road));

        if ('E' === $prefix) {
            return self::GREEN;
        }

        return self::SIGNS[$code][$prefix] ?? self::NEUTRAL;
    }
}
