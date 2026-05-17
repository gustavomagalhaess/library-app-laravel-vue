<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tracked_jobs` is the user-facing status table for every queued job we
 * dispatch. Laravel's own `jobs` / `failed_jobs` tables track the framework's
 * job lifecycle (or, in our Redis setup, the equivalent Redis keys), but they
 * are opaque to the SPA. We need our own row keyed by a UUID so the frontend
 * can poll progress and receive a result payload (e.g. a signed download URL
 * for prepared file downloads).
 *
 * One row per dispatch:
 *   - uuid              public identifier used by GET /api/jobs/{uuid}
 *   - user_id           who initiated it (nullable for system jobs)
 *   - type              human-readable kind (e.g. media.create, media.delete,
 *                       author.update, media.download.prepare)
 *   - resource_id       the optimistic UUID/ID the SPA can use to reconcile
 *                       (e.g. the future media uuid, the author id, …)
 *   - status            queued | processing | completed | failed
 *   - message           short error / status message for the UI
 *   - payload           original request snapshot (debug + retry context)
 *   - result            JSON for the SPA (the persisted record, signed URL, …)
 *   - started_at        when the worker picked it up
 *   - finished_at       when it terminated (success or failure)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 64)->index();
            $table->string('resource_id', 64)->nullable()->index();
            $table->string('status', 16)->default('queued')->index();
            $table->string('message')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_jobs');
    }
};
