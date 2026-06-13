<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classifications are attached to media items by UUID, regardless of the underlying type — so a future Movie or Music
 * row can share the same classifications table without any schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_classifications', function (Blueprint $table): void {
            $table->uuid('media_id');
            $table->foreignId('classification_id')->constrained('classifications')->cascadeOnDelete();
            $table->primary(['media_id', 'classification_id']);

            $table->foreign('media_id')
                ->references('uuid')
                ->on('media')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_classifications');
    }
};
