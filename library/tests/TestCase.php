<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed the Spatie roles/permissions registry before every test that uses
     * RefreshDatabase. The seeder is idempotent (findOrCreate everywhere) and
     * cheap (a handful of inserts), so running it unconditionally keeps tests
     * that hit role-aware code paths (CreateNewUser::assignRole('reader'),
     * MediaPolicy gates, …) green without each file having to hand-seed.
     *
     * Tests that don't use RefreshDatabase keep the default behaviour.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent ViteManifestNotFoundException in HTTP tests that render pages.
        // Feature tests don't need real JS/CSS assets — only Dusk browser tests do.
        $this->withoutVite();

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }
    }
}
