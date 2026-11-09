<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Repository;

use App\SharedKernel\Domain\Repository\NoResultException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * @template T of object
 */
abstract class AbstractRepository
{
    public function __construct(protected readonly EntityManagerInterface $entityManager)
    {
    }

    /** @return class-string<T> */
    abstract protected function getEntity(): string;

    /** @return T */
    protected function find(mixed $id): object
    {
        return $this->repository()->find($id)
            ?? throw new NoResultException(sprintf('%s "%s" not found.', $this->getEntity(), (string) $id));
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return T
     */
    protected function findOneBy(array $criteria): object
    {
        return $this->repository()->findOneBy($criteria)
            ?? throw new NoResultException(sprintf('%s not found.', $this->getEntity()));
    }

    /** @return EntityRepository<T> */
    protected function repository(): EntityRepository
    {
        return $this->entityManager->getRepository($this->getEntity());
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
