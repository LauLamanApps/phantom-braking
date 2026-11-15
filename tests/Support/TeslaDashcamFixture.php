<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Builds a small MP4 laid out like a Tesla dashcam clip: an H.264 track whose every frame starts with
 * an SEI unit carrying dashcam.proto's SeiMetadata. The frames themselves are dummy slices; nothing
 * decodes them, the reader only walks the sample tables.
 */
final class TeslaDashcamFixture
{
    public const int TIMESCALE = 1000;
    public const int FRAME_DURATION = 25; // ms: 40 fps

    /**
     * @param list<array<string, int|float|bool>> $frames  SeiMetadata fields per frame, by name
     * @param int                                 $trimmed leading frames an edit list hides, as in a trimmed clip
     */
    public static function create(array $frames, int $trimmed = 0): string
    {
        $samples = array_map(static fn (array $frame): string => self::nal("\x06".self::escape(self::seiPayload(self::protobuf($frame))))
            .self::nal("\x01\x9a\x00\x00\x00\x01\x02"), $frames);

        $ftyp = self::box('ftyp', 'isom'.pack('N', 512).'isomavc1');
        $mdat = self::box('mdat', implode('', $samples));
        // moov comes after mdat (as in Tesla's files); chunk offsets point into the mdat payload.
        $firstOffset = \strlen($ftyp) + 8;
        $moov = self::moov($samples, $firstOffset, $trimmed);

        $path = (string) tempnam(sys_get_temp_dir(), 'tesla_');
        file_put_contents($path, $ftyp.$mdat.$moov);

        return $path;
    }

    /** @param list<string> $samples */
    private static function moov(array $samples, int $firstOffset, int $trimmed): string
    {
        $count = \count($samples);
        $duration = $count * self::FRAME_DURATION;

        $avcC = self::box('avcC', "\x01\x64\x00\x28\xff\xe1\x00\x04\x67\x64\x00\x28\x01\x00\x04\x68\xee\x3c\x80");
        $avc1 = self::box('avc1', str_repeat("\x00", 6)."\x00\x01".str_repeat("\x00", 16).pack('nn', 1280, 960)
            .pack('NN', 0x00480000, 0x00480000).pack('N', 0).pack('n', 1).str_repeat("\x00", 32).pack('n', 0x18).pack('n', 0xFFFF).$avcC);
        $stsd = self::fullBox('stsd', pack('N', 1).$avc1);
        // Two runs, so the reader has to walk them.
        $half = intdiv($count, 2);
        $stts = self::fullBox('stts', pack('N', 2).pack('NN', $half, self::FRAME_DURATION).pack('NN', $count - $half, self::FRAME_DURATION));
        $stsz = self::fullBox('stsz', pack('NN', 0, $count).implode('', array_map(static fn (string $s): string => pack('N', \strlen($s)), $samples)));
        // Two chunks of unequal size, to exercise the sample-to-chunk table.
        $firstChunk = max(1, intdiv($count, 3));
        $stsc = self::fullBox('stsc', pack('N', 2).pack('NNN', 1, $firstChunk, 1).pack('NNN', 2, $count - $firstChunk, 1));
        $secondOffset = $firstOffset + array_sum(array_map(strlen(...), \array_slice($samples, 0, $firstChunk)));
        $stco = self::fullBox('stco', pack('N', 2).pack('NN', $firstOffset, $secondOffset));
        $stbl = self::box('stbl', $stsd.$stts.$stsz.$stsc.$stco);

        $mdhd = self::fullBox('mdhd', pack('NNNN', 0, 0, self::TIMESCALE, $duration).pack('nn', 0x55C4, 0));
        $hdlr = self::fullBox('hdlr', pack('N', 0).'vide'.str_repeat("\x00", 12)."Video\x00");
        $minf = self::box('minf', self::box('vmhd', "\x00\x00\x00\x01".str_repeat("\x00", 8)).$stbl);
        $mdia = self::box('mdia', $mdhd.$hdlr.$minf);
        $edts = 0 === $trimmed ? '' : self::box('edts', self::fullBox('elst', pack('N', 1).pack('NN', $duration - $trimmed * self::FRAME_DURATION, $trimmed * self::FRAME_DURATION).pack('N', 0x00010000)));
        $trak = self::box('trak', $edts.$mdia);
        $mvhd = self::fullBox('mvhd', pack('NNNN', 0, 0, self::TIMESCALE, $duration).str_repeat("\x00", 80));

        return self::box('moov', $mvhd.$trak);
    }

    /** @param array<string, int|float|bool> $frame */
    private static function protobuf(array $frame): string
    {
        $fields = [
            'version' => [1, 'varint'], 'gear_state' => [2, 'varint'], 'frame_seq_no' => [3, 'varint'],
            'vehicle_speed_mps' => [4, 'float'], 'accelerator_pedal_position' => [5, 'float'], 'steering_wheel_angle' => [6, 'float'],
            'blinker_on_left' => [7, 'varint'], 'blinker_on_right' => [8, 'varint'], 'brake_applied' => [9, 'varint'],
            'autopilot_state' => [10, 'varint'], 'latitude_deg' => [11, 'double'], 'longitude_deg' => [12, 'double'],
            'heading_deg' => [13, 'double'], 'linear_acceleration_mps2_x' => [14, 'double'], 'linear_acceleration_mps2_y' => [15, 'double'],
            'linear_acceleration_mps2_z' => [16, 'double'],
        ];

        $message = '';
        foreach ($frame as $name => $value) {
            [$number, $type] = $fields[$name];
            $message .= match ($type) {
                'varint' => self::varint($number << 3).self::varint((int) $value),
                'float' => self::varint($number << 3 | 5).pack('g', (float) $value),
                'double' => self::varint($number << 3 | 1).pack('e', (float) $value),
            };
        }

        return $message;
    }

    /** SEI message: type 5 (user data unregistered), its size, Tesla's 0x42… 0x69 marker, the message, then RBSP trailing bits. */
    private static function seiPayload(string $message): string
    {
        $payload = "\x42\x42\x42\x69".$message;
        $size = \strlen($payload);

        return "\x05".str_repeat("\xff", intdiv($size, 255)).\chr($size % 255).$payload."\x80";
    }

    /** H.264 emulation prevention: no 00 00 0x (x ≤ 3) may appear inside a NAL unit. */
    private static function escape(string $rbsp): string
    {
        $out = '';
        $zeros = 0;
        foreach (str_split($rbsp) as $byte) {
            if ($zeros >= 2 && \ord($byte) <= 3) {
                $out .= "\x03";
                $zeros = 0;
            }
            $out .= $byte;
            $zeros = "\x00" === $byte ? $zeros + 1 : 0;
        }

        return $out;
    }

    private static function varint(int $value): string
    {
        $out = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $out .= \chr($value > 0 ? $byte | 0x80 : $byte);
        } while ($value > 0);

        return $out;
    }

    private static function nal(string $unit): string
    {
        return pack('N', \strlen($unit)).$unit;
    }

    private static function box(string $type, string $contents): string
    {
        return pack('N', 8 + \strlen($contents)).$type.$contents;
    }

    private static function fullBox(string $type, string $contents): string
    {
        return self::box($type, "\x00\x00\x00\x00".$contents);
    }
}
