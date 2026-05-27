<?php

declare(strict_types=1);

namespace App\Domain\Classification\Repositories;

use App\Domain\Classification\Models\Classification;
use Illuminate\Support\Collection;

interface ClassificationRepositoryInterface
{
    /** @return Collection<int, Classification> */
    public function list(): Collection;
}
