<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

/**
 * A rectangle to black out on a photo (usually a license plate), in fractions of
 * the image size so it survives scaling: x, y = top-left corner, w, h = size; all 0..1.
 */
final readonly class PlateMask
{
    private function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {
    }

    public static function fromArray(mixed $values): self
    {
        if (!\is_array($values) || 4 !== \count($values)) {
            throw new \InvalidArgumentException('A mask needs exactly x, y, width and height.');
        }
        [$x, $y, $w, $h] = array_map(static fn (mixed $v): float => is_numeric($v) ? (float) $v : throw new \InvalidArgumentException('Mask values must be numbers.'), array_values($values));

        if ($x < 0 || $y < 0 || $w <= 0 || $h <= 0 || $x + $w > 1.0001 || $y + $h > 1.0001) {
            throw new \InvalidArgumentException('Mask must lie within the image.');
        }

        return new self(round($x, 4), round($y, 4), round(min($w, 1 - $x), 4), round(min($h, 1 - $y), 4));
    }

    /**
     * @return list<self>
     */
    public static function listFromArray(mixed $list, int $max = 20): array
    {
        if (!\is_array($list)) {
            return [];
        }
        if (\count($list) > $max) {
            throw new \InvalidArgumentException(sprintf('At most %d masks per photo.', $max));
        }

        return array_values(array_map(self::fromArray(...), $list));
    }

    /** @return array{float, float, float, float} */
    public function toArray(): array
    {
        return [$this->x, $this->y, $this->width, $this->height];
    }
}
