<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\GasConsumer;
use App\Models\User;
use Database\Seeders\GasSetupSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The gas page must list every flat, not just the ones someone registered a meter for. */
class GasConsumerListTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_flat_appears_once_and_the_office_comes_first(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(MoneyCorePermissionSeeder::class);
        $this->seed(GasSetupSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        Flat::factory()->count(3)->create();

        $first = $this->actingAs($admin, 'sanctum')->getJson('/api/gas/consumers')->assertOk();
        $this->assertCount(4, $first->json());
        $this->assertTrue($first->json('0.is_internal'));
        $this->assertSame(3, collect($first->json())->whereNotNull('flat_id')->count());

        // a second call (and a flat added later) never duplicates
        Flat::factory()->create();
        $second = $this->actingAs($admin, 'sanctum')->getJson('/api/gas/consumers')->assertOk();
        $this->assertCount(5, $second->json());
        $this->assertSame(5, GasConsumer::count());
    }
}
