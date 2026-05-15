<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Media\Services\MediaService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public function index()
    {
        // Single parametric service — no more BookService import. To surface
        // counts for additional media types later, just add another call:
        //   $movieCount = $this->mediaService->count('movie');
        $bookCount = $this->mediaService->count('book');

        return Inertia::render('Dashboard', [
            'bookCount' => $bookCount,
        ]);
    }
}
