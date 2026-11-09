<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Filter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/** Lets any controller take an `IncidentFilterQuery $filter` argument. */
final class IncidentFilterQueryValueResolver implements ValueResolverInterface
{
    /** @return iterable<IncidentFilterQuery> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (IncidentFilterQuery::class !== $argument->getType()) {
            return [];
        }

        return [IncidentFilterQuery::fromQuery($request->query->all())];
    }
}
