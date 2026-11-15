<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Service;

/**
 * The H.264 video track of an MP4/MOV file: where each sample (frame) sits in the file and when the
 * player shows it. Reads only the sample tables in the "moov" box, never the video itself.
 */
final readonly class Mp4VideoTrack
{
    /** A moov box is a few hundred KB even for long clips; anything this large isn't one. */
    private const int MAX_MOOV_SIZE = 64 * 1024 * 1024;

    /**
     * @param int<1, 4>                    $nalLengthSize bytes before each NAL unit that give its length (avcC)
     * @param list<array{float, int, int}> $samples       [seconds into the video as the player counts them, file offset, size]
     */
    private function __construct(public int $nalLengthSize, public array $samples)
    {
    }

    /**
     * @param resource $file
     *
     * @throws \UnexpectedValueException when the file isn't an MP4 with an H.264 video track
     */
    public static function read($file): self
    {
        $moov = self::findTopLevelBox($file, 'moov');
        $movieTimescale = self::movieTimescale($moov);

        foreach (self::children($moov) as [$type, $trak]) {
            if ('trak' !== $type) {
                continue;
            }
            $mdia = self::child($trak, 'mdia');
            if (null === $mdia || 'vide' !== substr((string) self::child($mdia, 'hdlr'), 8, 4)) {
                continue;
            }

            return self::fromVideoTrak($trak, $mdia, $movieTimescale);
        }

        throw new \UnexpectedValueException('No video track.');
    }

    private static function fromVideoTrak(string $trak, string $mdia, int $movieTimescale): self
    {
        $stbl = self::child((string) self::child($mdia, 'minf'), 'stbl') ?? throw new \UnexpectedValueException('No sample table.');
        $timescale = self::mediaTimescale(self::child($mdia, 'mdhd') ?? throw new \UnexpectedValueException('No media header.'));
        [$startOffset, $mediaTime] = self::editList($trak, $movieTimescale);

        $nalLengthSize = self::nalLengthSize(self::child($stbl, 'stsd') ?? throw new \UnexpectedValueException('No sample description.'));
        $sizes = self::sampleSizes(self::child($stbl, 'stsz') ?? throw new \UnexpectedValueException('No sample sizes.'));
        $offsets = self::sampleOffsets($stbl, $sizes);
        $decodeTimes = self::decodeTimes(self::child($stbl, 'stts') ?? throw new \UnexpectedValueException('No sample times.'), \count($sizes));
        $compositionOffsets = self::compositionOffsets(self::child($stbl, 'ctts'), \count($sizes));

        $samples = [];
        foreach ($sizes as $i => $size) {
            $time = $startOffset + ($decodeTimes[$i] + $compositionOffsets[$i] - $mediaTime) / $timescale;
            $samples[] = [$time, $offsets[$i], $size];
        }

        return new self($nalLengthSize, $samples);
    }

    /** @param resource $file */
    private static function findTopLevelBox($file, string $wanted): string
    {
        $stat = fstat($file);
        $fileSize = false === $stat ? 0 : $stat['size'];
        $position = 0;
        while ($position + 8 <= $fileSize) {
            fseek($file, $position);
            $header = (string) fread($file, 16);
            if (\strlen($header) < 8) {
                break;
            }
            [$size, $type, $headerSize] = self::boxHeader($header, $fileSize - $position);
            if ($type === $wanted) {
                $length = $size - $headerSize;
                if ($length < 1 || $length > self::MAX_MOOV_SIZE) {
                    throw new \UnexpectedValueException('Empty or too large box.');
                }
                fseek($file, $position + $headerSize);

                return (string) fread($file, $length);
            }
            $position += $size;
        }

        throw new \UnexpectedValueException(sprintf('No %s box.', $wanted));
    }

    /** @return array{int, string, int} [box size, type, header size] */
    private static function boxHeader(string $bytes, int $remaining): array
    {
        $size = self::u32($bytes, 0);
        $type = substr($bytes, 4, 4);
        $headerSize = 8;
        if (1 === $size) {
            if (\strlen($bytes) < 16) {
                throw new \UnexpectedValueException('Truncated box header.');
            }
            $size = self::u64($bytes, 8);
            $headerSize = 16;
        } elseif (0 === $size) {
            $size = $remaining; // up to the end of the file
        }
        if ($size < $headerSize || $size > $remaining) {
            throw new \UnexpectedValueException('Invalid box size.');
        }

        return [$size, $type, $headerSize];
    }

    /** @return \Generator<int, array{string, string}> [type, contents] of each child box */
    private static function children(string $box, int $from = 0): \Generator
    {
        $position = $from;
        $length = \strlen($box);
        while ($position + 8 <= $length) {
            [$size, $type, $headerSize] = self::boxHeader(substr($box, $position, 16), $length - $position);
            yield [$type, substr($box, $position + $headerSize, $size - $headerSize)];
            $position += $size;
        }
    }

    private static function child(string $box, string $wanted): ?string
    {
        foreach (self::children($box) as [$type, $contents]) {
            if ($type === $wanted) {
                return $contents;
            }
        }

        return null;
    }

    private static function movieTimescale(string $moov): int
    {
        $mvhd = self::child($moov, 'mvhd') ?? throw new \UnexpectedValueException('No movie header.');

        return self::positive(self::u32($mvhd, 0 === \ord($mvhd[0]) ? 12 : 20));
    }

    private static function mediaTimescale(string $mdhd): int
    {
        return self::positive(self::u32($mdhd, 0 === \ord($mdhd[0]) ? 12 : 20));
    }

    /**
     * A trimmed clip keeps the frames before the cut (back to a keyframe) and hides them with an edit list.
     *
     * @return array{float, int} [seconds of empty edit before the media starts, media time the player starts at]
     */
    private static function editList(string $trak, int $movieTimescale): array
    {
        $elst = self::child((string) self::child($trak, 'edts'), 'elst');
        if (null === $elst) {
            return [0.0, 0];
        }

        $version = \ord($elst[0]);
        $count = self::u32($elst, 4);
        $entrySize = 1 === $version ? 20 : 12;
        $empty = 0;
        for ($i = 0; $i < $count && 8 + ($i + 1) * $entrySize <= \strlen($elst); ++$i) {
            $at = 8 + $i * $entrySize;
            $segmentDuration = 1 === $version ? self::u64($elst, $at) : self::u32($elst, $at);
            $mediaTime = 1 === $version ? self::s64($elst, $at + 8) : self::s32($elst, $at + 4);
            if (-1 !== $mediaTime) {
                return [$empty / $movieTimescale, $mediaTime];
            }
            $empty += $segmentDuration;
        }

        return [$empty / $movieTimescale, 0];
    }

    /** @return int<1, 4> */
    private static function nalLengthSize(string $stsd): int
    {
        // Version/flags and entry count, then the first sample entry: its header and the 78 bytes of a visual sample entry.
        $entry = substr($stsd, 8);
        [$size, $type] = self::boxHeader(substr($entry, 0, 16), \strlen($entry));
        if ('avc1' !== $type && 'avc3' !== $type) {
            throw new \UnexpectedValueException(sprintf('Not H.264 but %s.', $type));
        }
        foreach (self::children(substr($entry, 8, $size - 8), 78) as [$childType, $contents]) {
            if ('avcC' === $childType && \strlen($contents) > 4) {
                return (\ord($contents[4]) & 0x03) + 1;
            }
        }

        throw new \UnexpectedValueException('No avcC box.');
    }

    /** @return list<int> */
    private static function sampleSizes(string $stsz): array
    {
        $fixed = self::u32($stsz, 4);
        $count = self::u32($stsz, 8);
        if ($fixed > 0) {
            return array_fill(0, $count, $fixed);
        }
        if (\strlen($stsz) < 12 + 4 * $count) {
            throw new \UnexpectedValueException('Truncated sample sizes.');
        }

        return array_values(unpack('N*', substr($stsz, 12, 4 * $count)) ?: []);
    }

    /**
     * @param list<int> $sizes
     *
     * @return list<int>
     */
    private static function sampleOffsets(string $stbl, array $sizes): array
    {
        if (null !== $stco = self::child($stbl, 'stco')) {
            $chunkOffsets = array_values(unpack('N*', substr($stco, 8, 4 * self::u32($stco, 4))) ?: []);
        } elseif (null !== $co64 = self::child($stbl, 'co64')) {
            $chunkOffsets = [];
            for ($i = 0, $count = self::u32($co64, 4); $i < $count; ++$i) {
                $chunkOffsets[] = self::u64($co64, 8 + 8 * $i);
            }
        } else {
            throw new \UnexpectedValueException('No chunk offsets.');
        }

        $stsc = self::child($stbl, 'stsc') ?? throw new \UnexpectedValueException('No sample-to-chunk table.');
        $runs = [];
        for ($i = 0, $count = self::u32($stsc, 4); $i < $count; ++$i) {
            $runs[] = [self::u32($stsc, 8 + 12 * $i), self::u32($stsc, 12 + 12 * $i)]; // [first chunk (1-based), samples per chunk]
        }

        $chunks = [];
        foreach ($runs as $r => [$firstChunk, $perChunk]) {
            $lastChunk = isset($runs[$r + 1]) ? $runs[$r + 1][0] - 1 : \count($chunkOffsets);
            for ($chunk = $firstChunk; $chunk <= $lastChunk && isset($chunkOffsets[$chunk - 1]); ++$chunk) {
                $chunks[] = [$chunkOffsets[$chunk - 1], $perChunk];
            }
        }

        // Samples follow each other within a chunk; their sizes give each one's place.
        $sampleCount = \count($sizes);
        $result = [];
        $sample = 0;
        foreach ($chunks as [$offset, $perChunk]) {
            for ($i = 0; $i < $perChunk && $sample < $sampleCount; ++$i) {
                $result[] = $offset;
                $offset += $sizes[$sample++];
            }
        }
        if (\count($result) !== $sampleCount) {
            throw new \UnexpectedValueException('Chunk table does not cover every sample.');
        }

        return $result;
    }

    /** @return list<int> */
    private static function decodeTimes(string $stts, int $sampleCount): array
    {
        $times = [];
        $time = 0;
        for ($i = 0, $count = self::u32($stts, 4); $i < $count && \count($times) < $sampleCount; ++$i) {
            $run = self::u32($stts, 8 + 8 * $i);
            $delta = self::u32($stts, 12 + 8 * $i);
            for ($j = 0; $j < $run && \count($times) < $sampleCount; ++$j) {
                $times[] = $time;
                $time += $delta;
            }
        }

        return array_pad($times, $sampleCount, $time);
    }

    /** @return list<int> */
    private static function compositionOffsets(?string $ctts, int $sampleCount): array
    {
        $offsets = [];
        if (null !== $ctts) {
            for ($i = 0, $count = self::u32($ctts, 4); $i < $count && \count($offsets) < $sampleCount; ++$i) {
                $run = self::u32($ctts, 8 + 8 * $i);
                // Version 1 is signed; version 0 is meant to be unsigned, but writers put negative values in it too.
                $offset = self::s32($ctts, 12 + 8 * $i);
                for ($j = 0; $j < $run && \count($offsets) < $sampleCount; ++$j) {
                    $offsets[] = $offset;
                }
            }
        }

        return array_pad($offsets, $sampleCount, 0);
    }

    private static function u32(string $bytes, int $at): int
    {
        if (\strlen($bytes) < $at + 4) {
            throw new \UnexpectedValueException('Truncated box.');
        }

        return self::unpack('N', $bytes, $at);
    }

    private static function s32(string $bytes, int $at): int
    {
        $value = self::u32($bytes, $at);

        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    private static function u64(string $bytes, int $at): int
    {
        if (\strlen($bytes) < $at + 8) {
            throw new \UnexpectedValueException('Truncated box.');
        }

        return self::unpack('J', $bytes, $at);
    }

    private static function s64(string $bytes, int $at): int
    {
        return self::u64($bytes, $at); // PHP's 64-bit unpack is already two's complement
    }

    private static function unpack(string $format, string $bytes, int $at): int
    {
        $values = unpack($format, $bytes, $at);

        return false === $values ? throw new \UnexpectedValueException('Truncated box.') : (int) $values[1];
    }

    private static function positive(int $value): int
    {
        return $value > 0 ? $value : throw new \UnexpectedValueException('Invalid timescale.');
    }
}
