<?php

declare(strict_types=1);

namespace App\SharedKernel\Presentation\EventListener;

use App\SharedKernel\Domain\Repository\NoResultException;
use App\SharedKernel\Domain\Repository\RenamedIdRepositoryInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * A page that isn't found because its `{id}` was renamed (UUIDv4 → v7) moves permanently to the new ID, so
 * indexed and shared links to reports, photos and videos keep working.
 */
#[AsEventListener(KernelEvents::EXCEPTION)]
final readonly class RedirectRenamedIdListener
{
    public function __construct(
        private RenamedIdRepositoryInterface $renamedIds,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $id = $request->attributes->get('id');
        $route = $request->attributes->get('_route');
        if (!$event->getThrowable() instanceof NotFoundHttpException || !$request->isMethodSafe()
            || !\is_string($id) || !\is_string($route) || !Uuid::isValid($id)) {
            return;
        }

        try {
            $renamed = $this->renamedIds->getByOldId($id);
        } catch (NoResultException) {
            return;
        }

        /** @var array<string, mixed> $parameters */
        $parameters = $request->attributes->get('_route_params', []);
        $url = $this->urls->generate($route, ['id' => $renamed->getNewId()] + $parameters + $request->query->all());

        $event->setResponse(new RedirectResponse($url, RedirectResponse::HTTP_MOVED_PERMANENTLY));
    }
}
