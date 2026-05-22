<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Classification\Services\ClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassificationController extends Controller
{
    public function __construct(
        private readonly ClassificationService $classificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->classificationService->list()]);
    }
}
