<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Monitoring;

/**
 * Removes license plates from anything sent to error monitoring. Plates should
 * never be in an error to begin with (request bodies are not sent at all), so
 * this is the safety net: the RDW query string (?kenteken=…), plate fields in
 * structured data, and anything under a sensitive key.
 */
final class SensitiveDataScrubber
{
    public const string REDACTED = '[redacted]';

    private const array SENSITIVE_KEYS = ['kenteken', 'plate', 'licenseplate', 'license_plate', 'password', '_password', 'masks'];

    public static function text(string $value): string
    {
        // kenteken=AB-123-C / licensePlate=… / "licensePlate":"…" in URLs, messages and JSON fragments
        return (string) preg_replace(
            ['/((?:kenteken|licensePlate|license_plate|plate)=)[^&\s"\']+/i', '/("(?:kenteken|licensePlate|license_plate|plate)"\s*:\s*)"[^"]*"/i'],
            ['$1'.self::REDACTED, '$1"'.self::REDACTED.'"'],
            $value,
        );
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function data(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($key) && \in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = self::REDACTED;
            } elseif (\is_array($value)) {
                $data[$key] = self::data($value);
            } elseif (\is_string($value)) {
                $data[$key] = self::text($value);
            }
        }

        return $data;
    }
}
