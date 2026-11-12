<?php

declare(strict_types=1);

namespace App\Statistics\Presentation;

/**
 * The URL part of a make or model as the registry spells it: "MERCEDES-BENZ" → "mercedes-benz",
 * "ID.3" → "id-3". Lossy, so a page finds its model by comparing slugs, not by reversing one.
 */
final class VehicleModelSlug
{
    public static function of(string $name): string
    {
        $ascii = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');
    }

    /** @return array{make: string, model: string}|null both parts, or null when either has nothing URL-safe left */
    public static function params(string $make, string $model): ?array
    {
        $params = ['make' => self::of($make), 'model' => self::of($model)];

        return '' === $params['make'] || '' === $params['model'] ? null : $params;
    }
}
