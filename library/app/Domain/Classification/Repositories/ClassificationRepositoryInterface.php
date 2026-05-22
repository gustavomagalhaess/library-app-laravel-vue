<?php

declare(strict_types=1);

namespace App\Domain\Classification\Repositories;

use Illuminate\Support\Collection;

interface ClassificationRepositoryInterface
{
    /** @return Collection<int, \App\Domain\Classification\Models\Classification> */
    public function list(): Collection;
}
