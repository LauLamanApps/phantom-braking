<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Http;

use App\Incident\Domain\Exception\MediaNotFoundException;
use App\Incident\Domain\Exception\RangeNotSatisfiableException;
use App\Incident\Domain\Service\MediaStorageInterface;
use App\Incident\Domain\ValueObject\ByteRange;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streams a stored file to the browser, with byte-range support (206) for video seeking.
 * Whether the file may be served at all is decided by the calling controller.
 */
final readonly class MediaResponder
{
    public function __construct(private MediaStorageInterface $storage)
    {
    }

    /** @param bool $public false for admin-only media: never cached anywhere */
    public function respond(Request $request, string $key, string $contentType, string $filename, bool $public): Response
    {
        $range = ByteRange::fromHeader($request->headers->get('Range'));

        try {
            $object = $this->storage->open($key, $range);
        } catch (MediaNotFoundException $e) {
            throw new NotFoundHttpException(previous: $e);
        } catch (RangeNotSatisfiableException $e) {
            return new Response('', Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, ['Content-Range' => 'bytes */'.$e->totalSize]);
        }

        $response = new StreamedResponse(static function () use ($object): void {
            foreach ($object->chunks as $chunk) {
                echo $chunk;
                flush();
            }
        }, null === $object->range ? Response::HTTP_OK : Response::HTTP_PARTIAL_CONTENT);

        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Length', (string) $object->contentLength);
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename));
        if (null !== $object->range) {
            $response->headers->set('Content-Range', sprintf('bytes %d-%d/%d', $object->range[0], $object->range[1], $object->totalSize));
        }

        // Always revalidate: masks can change and media can be deleted on request.
        $response->headers->set('Cache-Control', $public ? 'public, no-cache' : 'no-store, private');

        return $response;
    }
}
