<?php

namespace Tests\Feature;

use App\Models\Flat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `php artisan db:where` exists because two XAMPP installs once led the app
 * to an old empty database while the real data sat untouched in the other.
 * Its one job is to make "empty schema" vs "real data" impossible to miss.
 */
class DatabaseWhereCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_connection_and_empty_verdict_when_there_is_no_business_data(): void
    {
        $this->artisan('db:where')
            ->expectsOutputToContain('CONNECTED')
            ->expectsOutputToContain('VERDICT: EMPTY')
            ->assertSuccessful();
    }

    public function test_reports_data_present_once_flats_exist(): void
    {
        Flat::factory()->create();

        $this->artisan('db:where')
            ->expectsOutputToContain('flats            1')
            ->expectsOutputToContain('VERDICT: DATA PRESENT')
            ->assertSuccessful();
    }

    public function test_lists_applied_and_total_migration_counts(): void
    {
        $this->artisan('db:where')
            ->expectsOutputToContain('applied of')
            ->assertSuccessful();
    }

    public function test_write_option_saves_the_report_to_a_file(): void
    {
        $file = base_path('DATABASE-STATUS.txt');
        // The real file is the user's last find-database.bat result — put it back afterwards.
        $original = file_exists($file) ? file_get_contents($file) : null;

        try {
            $this->artisan('db:where --write')->assertSuccessful();

            $this->assertFileExists($file);
            $this->assertStringContainsString('database location report', file_get_contents($file));
        } finally {
            $original === null ? @unlink($file) : file_put_contents($file, $original);
        }
    }
}
