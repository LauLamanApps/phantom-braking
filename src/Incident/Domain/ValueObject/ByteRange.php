<?php

declare(strict_types=1);

namespace App\Incident\Domain\ValueObject;

/**
 * A single HTTP byte range ("bytes=0-99", "bytes=100-", "bytes=-500"). Browsers
 * need range requests to seek in videos (Safari won't play video without them).
 */
final readonly class ByteRange
{
    private function __construct(
        public ?int $start,
        public ?int $end,
        public ?int $suffixLength,
    ) {
    }

    /** Null for no header, or one we don't support (e.g. multiple ranges): then serve the whole file. */
    public static function fromHeader(?string $header): ?self
    {
        if (null === $header || 1 !== preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m) || ('' === $m[1] && '' === $m[2])) {
            return null;
        }
        if ('' === $m[1]) {
            return (int) $m[2] > 0 ? new self(null, null, (int) $m[2]) : null;
        }
        $end = '' === $m[2] ? null : (int) $m[2];
        if (null !== $end && $end < (int) $m[1]) {
            return null;
        }

        return new self((int) $m[1], $end, null);
    }

    public function toHeader(): string
    {
        return null !== $this->suffixLength ? 'bytes=-'.$this->suffixLength : sprintf('bytes=%d-%s', $this->start, $this->end ?? '');
    }

    /**
     * Resolves against the file size.
     *
     * @return array{int, int}|null [first, last] byte (inclusive), or null when unsatisfiable
     */
    public function resolve(int $size): ?array
    {
        if (0 === $size) {
            return null;
        }
        if (null !== $this->suffixLength) {
            return [max(0, $size - $this->suffixLength), $size - 1];
        }
        if ($this->start >= $size) {
            return null;
        }

        return [(int) $this->start, min($this->end ?? $size - 1, $size - 1)];
    }
}
