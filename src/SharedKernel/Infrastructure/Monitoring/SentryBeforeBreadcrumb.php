<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Monitoring;

use Sentry\Breadcrumb;

/** `before_breadcrumb` for Sentry: breadcrumbs carry URLs of outgoing requests, e.g. the RDW lookup with the plate. */
final class SentryBeforeBreadcrumb
{
    public function __invoke(Breadcrumb $breadcrumb): Breadcrumb
    {
        if (null !== $breadcrumb->getMessage()) {
            $breadcrumb = $breadcrumb->withMessage(SensitiveDataScrubber::text($breadcrumb->getMessage()));
        }
        foreach (SensitiveDataScrubber::data($breadcrumb->getMetadata()) as $name => $value) {
            $breadcrumb = $breadcrumb->withMetadata((string) $name, $value);
        }

        return $breadcrumb;
    }
}
