<?php

declare(strict_types=1);

namespace App\Domain\Classification\Repositories;

use App\Domain\Classification\Models\Classification;
use Illuminate\Support\Collection;

final class EloquentClassificationRepository implements ClassificationRepositoryInterface
{
    public function list(): Collection
    {
        return Classification::orderBy('code')->get(['id', 'code', 'name']);
    }
}