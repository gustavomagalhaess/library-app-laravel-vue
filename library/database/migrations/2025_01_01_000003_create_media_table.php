<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Morph parent table that holds the fields common to every media type
 * (title, publication_year, file_path). The `uuid` is globally unique
 * across all media types and acts as the discriminator id used by the
 * Laravel morph relationship instead of the conventional `mediable_id`.
 *
 *  media.uuid  ←→  books.uuid  (1:1, when mediable_type = 'book')
 *                  movies.uuid (future)
 *                  music.uuid  (future)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->uuid()->primary();
            // Laravel morph alias ('book', 'movie', 'music' — see DomainServiceProvider::boot).
            $table->string('mediable_type', 32);
            $table->string('title');
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index('mediable_type');
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
