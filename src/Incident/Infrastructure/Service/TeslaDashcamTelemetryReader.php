<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Service;

use App\Incident\Domain\Service\FootageTelemetryReaderInterface;
use App\Incident\Domain\ValueObject\DrivingAutomation;
use App\Incident\Domain\ValueObject\FootageTelemetry;
use App\Incident\Domain\ValueObject\Gear;
use App\Incident\Domain\ValueObject\TelemetrySample;

/**
 * Tesla (firmware 2025.44.25 or later, HW3 or above) puts a protobuf message in an H.264 SEI
 * "user data unregistered" NAL unit before every frame, see https://github.com/teslamotors/dashcam
 * (dashcam.proto). We walk the frames with the MP4 sample tables, so each message gets the time the
 * player shows its frame at, also in a clip that was trimmed afterwards.
 */
final readonly class TeslaDashcamTelemetryReader implements FootageTelemetryReaderInterface
{
    private const int NAL_SEI = 6;
    private const int SEI_USER_DATA_UNREGISTERED = 5;
    /** Tesla's payload: one or more 0x42 bytes, then 0x69, then the protobuf message. */
    private const int MARKER_PADDING = 0x42;
    private const int MARKER = 0x69;
    /** Larger SEI units are not Tesla's (theirs are about 100 bytes). */
    private const int MAX_SEI_SIZE = 4096;

    public function read(string $path): ?FootageTelemetry
    {
        $file = @fopen($path, 'r');
        if (false === $file) {
            return null;
        }

        try {
            $track = Mp4VideoTrack::read($file);
            $samples = [];
            foreach ($track->samples as [$time, $offset, $size]) {
                $message = $this->seiMessage($file, $offset, $size, $track->nalLengthSize);
                // Frames before 0 were trimmed off (kept only to decode from the previous keyframe).
                if (null !== $message && $time > -0.005) {
                    $samples[] = self::sample(max(0.0, $time), self::decodeProtobuf($message));
                }
            }
        } catch (\UnexpectedValueException) {
            return null;
        } finally {
            fclose($file);
        }

        return [] === $samples ? null : new FootageTelemetry('tesla', $samples);
    }

    /**
     * The protobuf message in the frame's SEI unit, if it has one. Tesla writes it before the picture
     * itself, so we stop at the first slice.
     *
     * @param resource  $file
     * @param int<1, 4> $lengthSize
     */
    private function seiMessage($file, int $offset, int $size, int $lengthSize): ?string
    {
        $position = 0;
        while ($position + $lengthSize < $size) {
            fseek($file, $offset + $position);
            $prefix = (string) fread($file, $lengthSize + 1);
            if (\strlen($prefix) < $lengthSize + 1) {
                return null;
            }
            $length = 0;
            for ($i = 0; $i < $lengthSize; ++$i) {
                $length = ($length << 8) | \ord($prefix[$i]);
            }
            if ($length < 1 || $position + $lengthSize + $length > $size) {
                return null;
            }

            $nalType = \ord($prefix[$lengthSize]) & 0x1F;
            if (1 === $nalType || 5 === $nalType) {
                return null; // a slice: the picture started without a message
            }
            if (self::NAL_SEI === $nalType && $length > 1 && $length <= self::MAX_SEI_SIZE) {
                $message = self::teslaMessage(self::removeEmulationPrevention((string) fread($file, $length - 1)));
                if (null !== $message) {
                    return $message;
                }
            }
            $position += $lengthSize + $length;
        }

        return null;
    }

    private static function removeEmulationPrevention(string $bytes): string
    {
        return (string) preg_replace('/\x00\x00\x03/', "\x00\x00", $bytes);
    }

    /** Walks the SEI messages in a NAL unit's payload (RBSP) to Tesla's user data. */
    private static function teslaMessage(string $rbsp): ?string
    {
        $position = 0;
        $length = \strlen($rbsp);
        while ($position < $length && "\x80" !== $rbsp[$position]) {
            $type = self::seiNumber($rbsp, $position);
            $size = self::seiNumber($rbsp, $position);
            $payload = substr($rbsp, $position, $size);
            $position += $size;
            if (self::SEI_USER_DATA_UNREGISTERED !== $type) {
                continue;
            }

            $start = 0;
            while ($start < \strlen($payload) && self::MARKER_PADDING === \ord($payload[$start])) {
                ++$start;
            }
            if ($start > 0 && $start < \strlen($payload) && self::MARKER === \ord($payload[$start])) {
                return substr($payload, $start + 1);
            }
        }

        return null;
    }

    /** SEI payload type and size: a run of 0xFF bytes (255 each) plus a last byte. */
    private static function seiNumber(string $bytes, int &$position): int
    {
        $value = 0;
        while ($position < \strlen($bytes)) {
            $byte = \ord($bytes[$position++]);
            $value += $byte;
            if (0xFF !== $byte) {
                return $value;
            }
        }

        throw new \UnexpectedValueException('Truncated SEI message.');
    }

    /** @return array<int, int|float> field number → value (varints, floats and doubles; Tesla's message has nothing else) */
    private static function decodeProtobuf(string $message): array
    {
        $fields = [];
        $position = 0;
        $length = \strlen($message);
        try {
            while ($position < $length) {
                $key = self::varint($message, $position);
                $field = $key >> 3;
                switch ($key & 0x07) {
                    case 0:
                        $fields[$field] = self::varint($message, $position);
                        break;
                    case 1:
                        $fields[$field] = self::fixed($message, $position, 8, 'e');
                        break;
                    case 5:
                        $fields[$field] = self::fixed($message, $position, 4, 'g');
                        break;
                    case 2:
                        $position += self::varint($message, $position);
                        break;
                    default:
                        return $fields; // a wire type we don't know: keep what we have
                }
            }
        } catch (\ValueError) {
            // the message was cut off: keep what we have
        }

        return $fields;
    }

    /** A little-endian float ('g', 4 bytes) or double ('e', 8 bytes). */
    private static function fixed(string $bytes, int &$position, int $size, string $format): float
    {
        if ($position + $size > \strlen($bytes)) {
            throw new \ValueError('Truncated number.');
        }
        $values = unpack($format, $bytes, $position);
        if (false === $values) {
            throw new \ValueError('Truncated number.');
        }
        $value = (float) $values[1];
        $position += $size;

        return $value;
    }

    private static function varint(string $bytes, int &$position): int
    {
        $value = 0;
        for ($shift = 0; $shift < 64; $shift += 7) {
            if ($position >= \strlen($bytes)) {
                throw new \ValueError('Truncated varint.');
            }
            $byte = \ord($bytes[$position++]);
            $value |= ($byte & 0x7F) << $shift;
            if ($byte < 0x80) {
                return $value;
            }
        }

        throw new \ValueError('Varint too long.');
    }

    /** @param array<int, int|float> $f fields of dashcam.proto's SeiMetadata; proto3 leaves out the ones that are 0 */
    private static function sample(float $time, array $f): TelemetrySample
    {
        return new TelemetrySample(
            time: $time,
            speed: (float) ($f[4] ?? 0.0) * 3.6,
            gear: match ($f[2] ?? 0) {
                1 => Gear::Drive,
                2 => Gear::Reverse,
                3 => Gear::Neutral,
                default => Gear::Park,
            },
            acceleratorPedal: (float) ($f[5] ?? 0.0),
            steeringAngle: (float) ($f[6] ?? 0.0),
            blinkerLeft: 1 === ($f[7] ?? 0),
            blinkerRight: 1 === ($f[8] ?? 0),
            brakePedal: 1 === ($f[9] ?? 0),
            automation: match ($f[10] ?? 0) {
                1 => DrivingAutomation::SelfDriving,
                2 => DrivingAutomation::LaneCentering,
                3 => DrivingAutomation::AdaptiveCruise,
                default => DrivingAutomation::None,
            },
            latitude: (float) ($f[11] ?? 0.0),
            longitude: (float) ($f[12] ?? 0.0),
            heading: isset($f[11]) || isset($f[12]) ? (float) ($f[13] ?? 0.0) : null,
            // Tesla's y axis points backwards (positive while braking), x to the right.
            longitudinalAcceleration: 0.0 - (float) ($f[15] ?? 0.0),
            lateralAcceleration: (float) ($f[14] ?? 0.0),
        );
    }
}
