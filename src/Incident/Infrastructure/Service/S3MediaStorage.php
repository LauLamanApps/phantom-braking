<?php

declare(strict_types=1);

namespace App\Incident\Infrastructure\Service;

use App\Incident\Domain\Exception\MediaNotFoundException;
use App\Incident\Domain\Exception\RangeNotSatisfiableException;
use App\Incident\Domain\Service\MediaObject;
use App\Incident\Domain\Service\MediaStorageInterface;
use App\Incident\Domain\Service\StorageUsage;
use App\Incident\Domain\ValueObject\ByteRange;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\MediaKind;
use AsyncAws\Core\Exception\Http\ClientException;
use AsyncAws\S3\Exception\NoSuchKeyException;
use AsyncAws\S3\S3Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Media in private S3-compatible buckets (Sliplane in production, RustFS in dev):
 * one for photos, one for videos, each with its own credentials (Sliplane issues keys
 * per bucket) on the same endpoint. Objects are never public; the app streams them
 * after its own checks.
 */
final readonly class S3MediaStorage implements MediaStorageInterface
{
    public function __construct(
        #[Autowire(service: 'app.media_s3_photo_client')]
        private S3Client $photoClient,
        #[Autowire(service: 'app.media_s3_video_client')]
        private S3Client $videoClient,
        #[Autowire('%env(MEDIA_S3_PHOTO_BUCKET)%')]
        private string $photoBucket,
        #[Autowire('%env(MEDIA_S3_VIDEO_BUCKET)%')]
        private string $videoBucket,
    ) {
    }

    public function store(IncidentId $incidentId, string $name, string $temporaryPath, string $contentType): string
    {
        $key = MediaKey::for($incidentId, $name);
        $this->put($key, $temporaryPath, $contentType);
        unlink($temporaryPath);

        return $key;
    }

    /** Uploads a local file under an exact key (also used to import existing local media). */
    public function put(string $key, string $path, string $contentType): void
    {
        $body = fopen($path, 'r');
        if (false === $body) {
            throw new \RuntimeException('Media file could not be read for upload.');
        }

        try {
            $this->client(MediaKind::fromKey($key))->putObject([
                'Bucket' => $this->bucket(MediaKind::fromKey($key)),
                'Key' => MediaKey::assertSafe($key),
                'Body' => $body,
                'ContentType' => $contentType,
            ])->resolve();
        } finally {
            if (\is_resource($body)) {
                fclose($body);
            }
        }
    }

    public function open(string $key, ?ByteRange $range = null): MediaObject
    {
        $input = ['Bucket' => $this->bucket(MediaKind::fromKey($key)), 'Key' => MediaKey::assertSafe($key)];
        if (null !== $range) {
            $input['Range'] = $range->toHeader();
        }

        $object = $this->client(MediaKind::fromKey($key))->getObject($input);
        try {
            $object->resolve();
        } catch (NoSuchKeyException $e) {
            throw new MediaNotFoundException('No such media.', previous: $e);
        } catch (ClientException $e) {
            if (404 === $e->getCode()) {
                throw new MediaNotFoundException('No such media.', previous: $e);
            }
            if (416 === $e->getCode()) {
                throw new RangeNotSatisfiableException(0);
            }
            throw $e;
        }

        $length = (int) $object->getContentLength();
        // "bytes 0-99/12345" → served range and total size
        if (null !== $range && 1 === preg_match('#^bytes (\d+)-(\d+)/(\d+)$#', (string) $object->getContentRange(), $m)) {
            return new MediaObject($object->getBody()->getChunks(), $length, (int) $m[3], [(int) $m[1], (int) $m[2]]);
        }

        return new MediaObject($object->getBody()->getChunks(), $length, $length);
    }

    public function withLocalCopy(string $key, callable $operation): mixed
    {
        $temporary = (string) tempnam(sys_get_temp_dir(), 'media_');
        try {
            $handle = fopen($temporary, 'w');
            if (false === $handle) {
                throw new \RuntimeException('Temporary file could not be written.');
            }
            foreach ($this->open($key)->chunks as $chunk) {
                fwrite($handle, $chunk);
            }
            fclose($handle);

            return $operation($temporary);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function delete(string $key): void
    {
        $kind = MediaKind::fromKey($key);
        $this->client($kind)->deleteObject(['Bucket' => $this->bucket($kind), 'Key' => MediaKey::assertSafe($key)])->resolve();
    }

    public function usage(): array
    {
        $usage = [];
        foreach (MediaKind::cases() as $kind) {
            $objects = 0;
            $bytes = 0;
            // getContents() follows the continuation tokens, so this covers the whole bucket.
            foreach ($this->client($kind)->listObjectsV2(['Bucket' => $this->bucket($kind)])->getContents() as $object) {
                ++$objects;
                $bytes += (int) $object->getSize();
            }
            $usage[] = new StorageUsage($kind, $this->bucket($kind), $objects, $bytes);
        }

        return $usage;
    }

    /**
     * Creates the buckets that don't exist yet (dev and first deploy).
     *
     * @return list<string> the buckets that were created
     */
    public function ensureBuckets(): array
    {
        $created = [];
        foreach (MediaKind::cases() as $kind) {
            $bucket = $this->bucket($kind);
            if (!$this->client($kind)->bucketExists(['Bucket' => $bucket])->isSuccess()) {
                $this->client($kind)->createBucket(['Bucket' => $bucket])->resolve();
                $created[] = $bucket;
            }
        }

        return $created;
    }


    private function bucket(MediaKind $kind): string
    {
        return MediaKind::Video === $kind ? $this->videoBucket : $this->photoBucket;
    }

    private function client(MediaKind $kind): S3Client
    {
        return MediaKind::Video === $kind ? $this->videoClient : $this->photoClient;
    }
}
