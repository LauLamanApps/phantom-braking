<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

/**
 * The driving data a car embeds in its own dashcam video (speed, pedals, steering, blinkers,
 * driver assistance, position), for an overlay on the published footage.
 */
final readonly class FootageTelemetry
{
    /** Enough for an overlay to follow along, without one row per video frame. */
    public const float INTERVAL = 0.1;

    /** @var list<TelemetrySample> */
    public array $samples;

    /**
     * Thins the samples to one per INTERVAL, but keeps every moment a pedal, blinker, gear or
     * assistance state changes, so even a short tap on the brake shows.
     *
     * @param string                $source  where the data comes from, e.g. "tesla"
     * @param list<TelemetrySample> $samples in any order
     */
    public function __construct(public string $source, array $samples)
    {
        usort($samples, static fn (TelemetrySample $a, TelemetrySample $b): int => $a->time <=> $b->time);

        $kept = [];
        $previous = null;
        foreach ($samples as $sample) {
            $last = $kept[\count($kept) - 1] ?? null;
            if (null === $last
                || $sample->time - $last->time >= self::INTERVAL - 0.0001
                || (null !== $previous && $sample->changedStateSince($previous))) {
                $kept[] = $sample;
            }
            $previous = $sample;
        }

        $this->samples = $kept;
    }

    public function isEmpty(): bool
    {
        return [] === $this->samples;
    }

    public function toJson(): string
    {
        return json_encode([
            'source' => $this->source,
            'samples' => array_map(static fn (TelemetrySample $sample): array => $sample->toArray(), $this->samples),
        ], \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
    }
}
