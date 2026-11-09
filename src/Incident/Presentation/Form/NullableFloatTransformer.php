<?php

declare(strict_types=1);

namespace App\Incident\Presentation\Form;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/** @implements DataTransformerInterface<float, string> */
final class NullableFloatTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        return null === $value ? '' : (string) $value;
    }

    public function reverseTransform(mixed $value): ?float
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_numeric($value)) {
            throw new TransformationFailedException('Not a number.');
        }

        return (float) $value;
    }
}
