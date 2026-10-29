<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\User;
use Database\Seeders\OperationsPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_downloads_a_sql_copy_containing_the_data_and_it_is_logged(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(OperationsPermissionSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $flat = Flat::factory()->create(['flat_no' => 'ZZ-99']);

        $res = $this->actingAs($admin, 'sanctum')->get('/api/backup/download');
        $res->assertOk();
        $sql = $res->streamedContent();

        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO', $sql);
        $this->assertStringContainsString('ZZ-99', $sql);
        $this->assertDatabaseHas('audit_logs', ['event' => 'backup', 'user_id' => $admin->id]);
    }

    public function test_nobody_else_can_download_it(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(OperationsPermissionSeeder::class);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager');
        $this->actingAs($manager, 'sanctum')->get('/api/backup/download')->assertForbidden();
    }
}
