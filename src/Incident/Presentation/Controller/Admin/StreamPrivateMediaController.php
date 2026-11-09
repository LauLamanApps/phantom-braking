<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Controller\Admin;

use App\Incident\Domain\Repository\IncidentRepositoryInterface;
use App\Incident\Domain\ValueObject\IncidentId;
use App\Incident\Domain\ValueObject\PhotoId;
use App\Incident\Presentation\Http\MediaResponder;
use App\SharedKernel\Domain\Repository\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin-only access to media regardless of moderation status: footage, a photo's
 * private original, or its masked copy. Never cached anywhere.
 */
#[Route('/admin/media/{kind}/{id}', name: 'admin_media', requirements: ['kind' => 'footage|photo-original|photo-masked'], methods: ['GET'])]
final class StreamPrivateMediaController extends AbstractController
{
    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly MediaResponder $responder,
    ) {
    }

    public function __invoke(Request $request, string $kind, string $id): Response
    {
        try {
            if ('footage' === $kind) {
                $footage = $this->incidents->get(IncidentId::fromString($id))->getFootage();
                [$path, $mimeType] = [$footage->getPath(), $footage->getMimeType()];
            } else {
                $photoId = PhotoId::fromString($id);
                $photo = $this->incidents->getByPhoto($photoId)->getPhoto($photoId);
                [$path, $mimeType] = ['photo-original' === $kind ? $photo->getPath() : $photo->getPublishedPath(), $photo->getMimeType()];
            }
        } catch (NoResultException|\InvalidArgumentException|\DomainException $e) {
            throw $this->createNotFoundException(previous: $e);
        }

        if (null === $path) {
            throw $this->createNotFoundException();
        }

        return $this->responder->respond($request, $path, (string) $mimeType, basename($path), public: false);
    }
}
