<?php

declare(strict_types=1);

namespace App\Domain\Classification\Services;

use App\Domain\Classification\Repositories\ClassificationRepositoryInterface;
use Illuminate\Support\Collection;

final readonly class ClassificationService
{
    public function __construct(
        private ClassificationRepositoryInterface $repository,
    ) {}

    public function list(): Collection
    {
        return $this->repository->list();
    }
}
