<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Book\Services\BookService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BookService $books,
    ) {}

    public function index()
    {
        $bookCount = $this->books->count();

        return Inertia::render('Dashboard', [
            'bookCount' => $bookCount,
        ]);
    }
}
