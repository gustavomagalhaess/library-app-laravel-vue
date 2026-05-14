<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * After the Media refactor, `books` only holds book-specific columns.
 * The common columns (title, publication_year, file_path) live in `media`,
 * and `books.uuid` matches the corresponding `media.uuid`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->unsignedSmallInteger('pages')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
