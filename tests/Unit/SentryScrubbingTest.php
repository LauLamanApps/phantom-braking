<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\SharedKernel\Infrastructure\Monitoring\SentryBeforeSend;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;

final class SentryScrubbingTest extends TestCase
{
    public function testNoPlateLeavesForSentry(): void
    {
        $event = Event::createEvent();
        $event->setMessage('Lookup failed for https://opendata.rdw.nl/resource/m9d7-ebf2.json?kenteken=GVN31K');
        $event->setExceptions([new ExceptionDataBag(new \RuntimeException('HTTP/2 503 returned for "https://opendata.rdw.nl/resource/m9d7-ebf2.json?kenteken=GVN31K".'))]);
        $event->setRequest([
            'url' => 'https://phantom-braking.dev/nl/reports?make=TESLA',
            'query_string' => 'kenteken=GVN31K&make=TESLA',
            'data' => ['report_incident' => ['licensePlate' => 'GV-N3-1K', 'roadNumber' => 'A2'], 'plate' => 'GVN31K'],
        ]);
        $event->setExtra(['payload' => '{"country":"NL","plate":"GVN31K"}']);
        $event->setBreadcrumb([new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_HTTP, 'http', null, [
            'url' => 'https://opendata.rdw.nl/resource/8ys7-d773.json?kenteken=GVN31K',
            'method' => 'GET',
        ])]);

        $scrubbed = (new SentryBeforeSend())($event);
        $all = json_encode([
            $scrubbed->getMessage(),
            array_map(static fn (ExceptionDataBag $e): string => $e->getValue(), $scrubbed->getExceptions()),
            $scrubbed->getRequest(),
            $scrubbed->getExtra(),
            array_map(static fn (Breadcrumb $b): array => $b->getMetadata(), $scrubbed->getBreadcrumbs()),
        ], \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('GVN31K', $all);
        self::assertStringNotContainsString('GV-N3-1K', $all);
        // Everything else stays useful for debugging.
        self::assertStringContainsString('make=TESLA', $all);
        self::assertStringContainsString('A2', $all);
        self::assertStringContainsString('m9d7-ebf2.json?kenteken=[redacted]', $all);
    }
}
