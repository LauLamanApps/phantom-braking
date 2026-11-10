<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Service;

use AsyncAws\Core\HttpClient\AwsRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The HTTP client for the S3 clients. AsyncAws only adds its own retries when it creates the HTTP client itself;
 * we pass the framework's (profiler, Sentry breadcrumbs), so the retries are added here. Without them, one stalled
 * connection to the storage meant a minute of waiting (PHP's default_socket_timeout) and then an error.
 */
final class S3HttpClientFactory
{
    /** Seconds without any data before a request is given up (and retried); a transfer that is moving never hits it. */
    public const float IDLE_TIMEOUT = 10.0;
    public const int MAX_RETRIES = 2;

    public static function create(HttpClientInterface $client, int $retryDelayMs = 500): HttpClientInterface
    {
        return new RetryableHttpClient(
            $client->withOptions(['timeout' => self::IDLE_TIMEOUT]),
            new AwsRetryStrategy(delayMs: $retryDelayMs),
            self::MAX_RETRIES,
        );
    }
}
