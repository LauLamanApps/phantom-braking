<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Incident\Domain\ValueObject\DrivingAutomation;
use App\Incident\Domain\ValueObject\FootageTelemetry;
use App\Incident\Domain\ValueObject\Gear;
use App\Incident\Domain\ValueObject\TelemetrySample;
use App\Incident\Infrastructure\Service\TeslaDashcamTelemetryReader;
use App\Tests\Support\TeslaDashcamFixture;
use PHPUnit\Framework\TestCase;

final class TeslaDashcamTelemetryReaderTest extends TestCase
{
    public function testReadsEveryFrameWithTheTimeThePlayerShowsIt(): void
    {
        $path = TeslaDashcamFixture::create([
            self::frame(0, speed: 25.0),
            self::frame(1, speed: 24.0, extra: [
                'accelerator_pedal_position' => 12.5,
                'steering_wheel_angle' => -30.0,
                'blinker_on_left' => true,
                'brake_applied' => true,
                'linear_acceleration_mps2_x' => 0.5,
                'linear_acceleration_mps2_y' => 4.9,
            ]),
            self::frame(2, speed: 23.0, extra: ['gear_state' => 2, 'autopilot_state' => 3]),
        ]);

        $telemetry = (new TeslaDashcamTelemetryReader())->read($path);
        unlink($path);

        self::assertNotNull($telemetry);
        self::assertSame('tesla', $telemetry->source);
        // Each state change is kept even though the frames are only 25 ms apart.
        self::assertSame([0.0, 0.025, 0.05], array_map(static fn (TelemetrySample $s): float => $s->time, $telemetry->samples));

        [$first, $braking, $reversing] = $telemetry->samples;
        self::assertEqualsWithDelta(90.0, $first->speed, 0.01);
        self::assertSame(Gear::Drive, $first->gear);
        self::assertSame(DrivingAutomation::SelfDriving, $first->automation);
        self::assertFalse($first->brakePedal);
        // Full precision in the file, rounded to ~11 m like the report's own location.
        self::assertSame([52.0321, 6.3044], [$first->latitude, $first->longitude]);
        self::assertEqualsWithDelta(188.246, $first->heading, 0.001);

        self::assertEqualsWithDelta(12.5, $braking->acceleratorPedal, 0.001);
        self::assertEqualsWithDelta(-30.0, $braking->steeringAngle, 0.001);
        self::assertTrue($braking->blinkerLeft);
        self::assertFalse($braking->blinkerRight);
        self::assertTrue($braking->brakePedal);
        // Tesla's y axis points backwards: positive y is braking, so it becomes negative acceleration.
        self::assertSame(-4.9, $braking->longitudinalAcceleration);
        self::assertSame(0.5, $braking->lateralAcceleration);

        self::assertSame(Gear::Reverse, $reversing->gear);
        self::assertSame(DrivingAutomation::AdaptiveCruise, $reversing->automation);
    }

    public function testFieldsProto3LeavesOutAreZero(): void
    {
        $path = TeslaDashcamFixture::create([['version' => 1, 'frame_seq_no' => 7]]);

        $sample = (new TeslaDashcamTelemetryReader())->read($path)?->samples[0];
        unlink($path);

        self::assertNotNull($sample);
        self::assertSame(0.0, $sample->speed);
        self::assertSame(Gear::Park, $sample->gear);
        self::assertSame(DrivingAutomation::None, $sample->automation);
        self::assertNull($sample->latitude, 'no GPS fix is not a position at 0, 0');
        self::assertNull($sample->heading);
        self::assertSame(0.0, $sample->longitudinalAcceleration);
    }

    public function testFramesTrimmedOffByAnEditListAreSkippedAndTimesStartAtTheCut(): void
    {
        $frames = array_map(static fn (int $i): array => self::frame($i, speed: 10.0 + $i), range(0, 11));
        $path = TeslaDashcamFixture::create($frames, trimmed: 4);

        $telemetry = (new TeslaDashcamTelemetryReader())->read($path);
        unlink($path);

        self::assertNotNull($telemetry);
        // Frame 4 is shown first, at 0 s; then one sample per 0.1 s (every 4th frame at 40 fps).
        self::assertSame([0.0, 0.1], array_map(static fn (TelemetrySample $s): float => $s->time, $telemetry->samples));
        self::assertEqualsWithDelta(14.0 * 3.6, $telemetry->samples[0]->speed, 0.01);
        self::assertEqualsWithDelta(18.0 * 3.6, $telemetry->samples[1]->speed, 0.01);
    }

    public function testVideosWithoutTeslaDataAndOtherFilesGiveNothing(): void
    {
        $reader = new TeslaDashcamTelemetryReader();
        $garbage = (string) tempnam(sys_get_temp_dir(), 'garbage_');
        file_put_contents($garbage, random_bytes(2048));

        self::assertNull($reader->read(__DIR__.'/../fixtures/dashcam.mp4'), 'an H.264 MP4 without SEI data');
        self::assertNull($reader->read($garbage));
        self::assertNull($reader->read(__DIR__.'/../../composer.json'));
        self::assertNull($reader->read('/does/not/exist.mp4'));
        unlink($garbage);
    }

    public function testATruncatedFileGivesNothingInsteadOfFailing(): void
    {
        $path = TeslaDashcamFixture::create([self::frame(0, speed: 20.0), self::frame(1, speed: 20.0)]);
        file_put_contents($path, substr((string) file_get_contents($path), 0, -40));

        self::assertNull((new TeslaDashcamTelemetryReader())->read($path));
        unlink($path);
    }

    public function testJsonForTheOverlay(): void
    {
        $telemetry = new FootageTelemetry('tesla', [new TelemetrySample(time: 1.23456, speed: 87.654, gear: Gear::Drive, latitude: 52.123456, longitude: 5.987654)]);

        self::assertSame(
            '{"source":"tesla","samples":[{"t":1.235,"speed":87.7,"gear":"D","accelerator":null,"steering":null,"blinkerLeft":false,"blinkerRight":false,"brake":false,"automation":"none","lat":52.1235,"lon":5.9877,"heading":null,"accelLong":null,"accelLat":null}]}',
            $telemetry->toJson(),
        );
    }

    public function testThinsToTenPerSecondButKeepsEveryStateChange(): void
    {
        $samples = [];
        for ($i = 0; $i < 40; ++$i) {
            // 40 fps for a second, with one brief tap on the brake pedal at frame 21 (0.525 s): kept, and its release too.
            $samples[] = new TelemetrySample(time: $i * 0.025, speed: 50.0, brakePedal: 21 === $i);
        }

        $times = array_map(static fn (TelemetrySample $s): float => $s->time, (new FootageTelemetry('tesla', $samples))->samples);

        self::assertSame([0.0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.525, 0.55, 0.65, 0.75, 0.85, 0.95], array_map(static fn (float $t): float => round($t, 3), $times));
    }

    /**
     * @param array<string, int|float|bool> $extra
     *
     * @return array<string, int|float|bool>
     */
    private static function frame(int $sequence, float $speed, array $extra = []): array
    {
        return array_merge([
            'version' => 1,
            'gear_state' => 1,
            'frame_seq_no' => 5641561 + $sequence,
            'vehicle_speed_mps' => $speed,
            'autopilot_state' => 1,
            // 52.0 and friends contain 00 00 bytes, so the fixture needs emulation prevention.
            'latitude_deg' => 52.03208714473003,
            'longitude_deg' => 6.304372056297922,
            'heading_deg' => 188.246035,
        ], $extra);
    }
}
