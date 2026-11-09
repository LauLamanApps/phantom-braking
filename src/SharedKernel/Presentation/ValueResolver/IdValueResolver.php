<?php

declare(strict_types=1);

namespace App\SharedKernel\Presentation\ValueResolver;

use App\SharedKernel\Domain\ValueObject\AbstractId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Turns a `{id}` route attribute into the typed ID value object. */
final class IdValueResolver implements ValueResolverInterface
{
    /** @return iterable<AbstractId> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $class = $argument->getType();
        if (null === $class || !is_subclass_of($class, AbstractId::class)) {
            return [];
        }

        $value = $request->attributes->get($argument->getName());
        if (!\is_string($value)) {
            return [];
        }

        try {
            return [$class::fromString($value)];
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }
}
