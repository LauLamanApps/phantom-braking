<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Monitoring;

use Sentry\Event;
use Sentry\EventHint;

/** `before_send` for Sentry: scrubs plates from messages, exceptions, request data, extra data and breadcrumbs. */
final class SentryBeforeSend
{
    public function __invoke(Event $event, ?EventHint $hint = null): Event
    {
        if (null !== $event->getMessage()) {
            $event->setMessage(SensitiveDataScrubber::text($event->getMessage()));
        }
        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(SensitiveDataScrubber::text($exception->getValue()));
        }
        $event->setRequest(SensitiveDataScrubber::data($event->getRequest()));
        $event->setExtra(SensitiveDataScrubber::data($event->getExtra()));
        $event->setBreadcrumb(array_map((new SentryBeforeBreadcrumb())(...), $event->getBreadcrumbs()));

        return $event;
    }
}
