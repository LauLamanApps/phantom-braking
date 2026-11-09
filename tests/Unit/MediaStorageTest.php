<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Incident\Domain\Exception\MediaNotFoundException;
use App\Incident\Domain\Exception\RangeNotSatisfiableException;
use App\Incident\Domain\ValueObject\ByteRange;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\MediaKind;
use App\Incident\Infrastructure\Service\LocalMediaStorage;
use App\Incident\Infrastructure\Service\MediaKey;
use App\Incident\Infrastructure\Service\S3MediaStorage;
use App\Incident\Presentation\Admin\StorageMonitor;
use AsyncAws\S3\S3Client;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MediaStorageTest extends TestCase
{
    /** @param array{int, int}|null $expected */
    #[TestWith(['bytes=0-99', 1000, [0, 99]])]
    #[TestWith(['bytes=990-', 1000, [990, 999]])]
    #[TestWith(['bytes=-10', 1000, [990, 999]])]
    #[TestWith(['bytes=500-5000', 1000, [500, 999]])]
    #[TestWith(['bytes=1000-', 1000, null])]
    public function testByteRangeResolves(string $header, int $size, ?array $expected): void
    {
        self::assertSame($expected, ByteRange::fromHeader($header)?->resolve($size));
    }

    #[TestWith([null])]
    #[TestWith(['bytes=0-1,5-9'])]
    #[TestWith(['bytes=9-5'])]
    #[TestWith(['items=0-5'])]
    public function testUnsupportedRangeHeadersMeanWholeFile(?string $header): void
    {
        self::assertNull(ByteRange::fromHeader($header));
    }

    public function testLocalStorageStreamsRanges(): void
    {
        $dir = sys_get_temp_dir().'/media-test-'.uniqid();
        $storage = new LocalMediaStorage($dir, new Filesystem());
        $tmp = (string) tempnam(sys_get_temp_dir(), 'm');
        file_put_contents($tmp, '0123456789');

        $key = $storage->store(IncidentId::generate(), 'footage.mp4', $tmp, 'video/mp4');
        self::assertFileDoesNotExist($tmp, 'the temporary file is consumed');

        $part = $storage->open($key, ByteRange::fromHeader('bytes=2-5'));
        self::assertSame('2345', implode('', iterator_to_array($part->chunks)));
        self::assertSame([4, 10, [2, 5]], [$part->contentLength, $part->totalSize, $part->range]);
        self::assertSame('0123456789', implode('', iterator_to_array($storage->open($key)->chunks)));
        self::assertSame(10, $storage->withLocalCopy($key, static fn (string $path): int => (int) filesize($path)));

        try {
            $storage->open($key, ByteRange::fromHeader('bytes=50-'));
            self::fail('Expected unsatisfiable range');
        } catch (RangeNotSatisfiableException $e) {
            self::assertSame(10, $e->totalSize);
        }

        $storage->delete($key);
        $this->expectException(MediaNotFoundException::class);
        $storage->open($key);
    }

    public function testS3StorageSpeaksS3(): void
    {
        $requests = [];
        $responses = [
            new MockResponse('', ['http_code' => 200]),                                                    // PUT
            new MockResponse('2345', ['http_code' => 206, 'response_headers' => ['Content-Length' => '4', 'Content-Range' => 'bytes 2-5/10']]), // GET range
            new MockResponse('', ['http_code' => 204]),                                                    // DELETE
            new MockResponse('<Error><Code>NoSuchKey</Code><Message>gone</Message></Error>', ['http_code' => 404]), // GET after delete
        ];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [$method, $url, $options['normalized_headers']['range'][0] ?? null, $options['normalized_headers']['content-type'][0] ?? null];

            return array_shift($responses) ?? throw new \LogicException('Unexpected extra S3 request.');
        });
        $client = new S3Client(['endpoint' => 'http://s3.test', 'region' => 'eu-central-1', 'accessKeyId' => 'k', 'accessKeySecret' => 's', 'pathStyleEndpoint' => 'true'], null, $http);
        $storage = new S3MediaStorage($client, $client, 'photos', 'videos');

        $tmp = (string) tempnam(sys_get_temp_dir(), 'm');
        file_put_contents($tmp, '0123456789');
        $key = $storage->store(IncidentId::generate(), 'footage.mp4', $tmp, 'video/mp4');
        self::assertFileDoesNotExist($tmp);

        $part = $storage->open($key, ByteRange::fromHeader('bytes=2-5'));
        self::assertSame('2345', implode('', iterator_to_array($part->chunks)));
        self::assertSame([4, 10, [2, 5]], [$part->contentLength, $part->totalSize, $part->range]);

        $storage->delete($key);

        self::assertSame(['PUT', 'http://s3.test/videos/'.$key, null, 'Content-Type: video/mp4'], $requests[0], 'videos go to the video bucket');
        self::assertSame(['GET', 'http://s3.test/videos/'.$key, 'Range: bytes=2-5'], \array_slice($requests[1], 0, 3));
        self::assertSame('DELETE', $requests[2][0]);

        $this->expectException(MediaNotFoundException::class);
        $storage->open($key);
    }

    public function testS3PhotosGoToThePhotoBucketAndUsageIsSummedPerBucket(): void
    {
        $urls = [];
        $listing = static fn (string ...$sizes): string => '<?xml version="1.0"?><ListBucketResult><IsTruncated>false</IsTruncated><KeyCount>'.\count($sizes).'</KeyCount>'
            .implode('', array_map(static fn (string $size): string => '<Contents><Key>k'.$size.'</Key><Size>'.$size.'</Size></Contents>', $sizes))
            .'</ListBucketResult>';
        $http = new MockHttpClient(static function (string $method, string $url) use (&$urls, $listing): MockResponse {
            $urls[] = $method.' '.strtok($url, '?');

            return match (true) {
                str_contains($url, 'list-type=2') && str_contains($url, '/photos') => new MockResponse($listing('100', '250')),
                str_contains($url, 'list-type=2') => new MockResponse($listing('5000')),
                default => new MockResponse(''),
            };
        });
        $client = new S3Client(['endpoint' => 'http://s3.test', 'region' => 'eu-central-1', 'accessKeyId' => 'k', 'accessKeySecret' => 's', 'pathStyleEndpoint' => 'true'], null, $http);
        $storage = new S3MediaStorage($client, $client, 'photos', 'videos');

        $tmp = (string) tempnam(sys_get_temp_dir(), 'm');
        file_put_contents($tmp, 'jpeg');
        $key = $storage->store(IncidentId::generate(), 'photo-x-public.jpg', $tmp, 'image/jpeg');
        $storage->delete($key);
        self::assertSame(['PUT http://s3.test/photos/'.$key, 'DELETE http://s3.test/photos/'.$key], $urls);

        $usage = $storage->usage();
        self::assertSame(['photos', 2, 350], [$usage[0]->location, $usage[0]->objects, $usage[0]->bytes]);
        self::assertSame(['videos', 1, 5000], [$usage[1]->location, $usage[1]->objects, $usage[1]->bytes]);
    }

    public function testEachBucketIsAccessedWithItsOwnCredentials(): void
    {
        $signedWith = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$signedWith): MockResponse {
            // AWS SigV4: "Authorization: AWS4-HMAC-SHA256 Credential=<access key>/<date>/…"
            preg_match('#Credential=([^/]+)/#', $options['normalized_headers']['authorization'][0] ?? '', $m);
            $signedWith[] = [$method, parse_url($url, \PHP_URL_PATH), $m[1] ?? null];

            return new MockResponse('');
        });
        $client = static fn (string $key): S3Client => new S3Client(['endpoint' => 'http://s3.test', 'region' => 'eu-central-1', 'accessKeyId' => $key, 'accessKeySecret' => 's', 'pathStyleEndpoint' => 'true'], null, $http);
        $storage = new S3MediaStorage($client('photo-key'), $client('video-key'), 'photos', 'videos');

        $storage->delete(MediaKey::for(IncidentId::generate(), 'photo-x.jpg'));
        $storage->delete(MediaKey::for(IncidentId::generate(), 'footage.mp4'));

        self::assertSame('photo-key', $signedWith[0][2]);
        self::assertStringStartsWith('/photos/', (string) $signedWith[0][1]);
        self::assertSame('video-key', $signedWith[1][2]);
        self::assertStringStartsWith('/videos/', (string) $signedWith[1][1]);
    }

    #[TestWith([0, 0])]
    #[TestWith([1_000_000_000, 0])]
    #[TestWith([1_000_000_001, 5])]
    #[TestWith([251_000_000_000, 5])]
    #[TestWith([251_000_000_001, 10])]
    public function testSliplaneCostEstimate(int $bytes, int $eur): void
    {
        self::assertSame($eur, StorageMonitor::estimate($bytes));
    }

    #[TestWith(['2026/11/abc/footage.mp4', MediaKind::Video])]
    #[TestWith(['2026/11/abc/photo-1.jpg', MediaKind::Photo])]
    #[TestWith(['2026/11/abc/photo-1-public.jpg', MediaKind::Photo])]
    public function testMediaKindFromKey(string $key, MediaKind $kind): void
    {
        self::assertSame($kind, MediaKind::fromKey($key));
    }
}
