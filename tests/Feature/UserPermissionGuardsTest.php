<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The Users page: nobody can hand out, or take over, more power than they hold; plus the small bugs found in a deep check. */
class UserPermissionGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RoleSeeder::class);
        foreach ([
            \Database\Seeders\FlatMasterPermissionSeeder::class, \Database\Seeders\DocumentPermissionSeeder::class, \Database\Seeders\StaffPermissionSeeder::class,
            MoneyCorePermissionSeeder::class, \Database\Seeders\GovernancePermissionSeeder::class, \Database\Seeders\CommunicationPermissionSeeder::class,
        ] as $s) {
            $this->seed($s);
        }
        $this->seed(RoleCatalogSeeder::class);
        // the admin holds every permission, as on the live system
        Role::where('name', 'admin')->first()->syncPermissions(Permission::all());
    }

    private function userWith(string $role, ?array $permissions = null): User
    {
        $u = User::factory()->create(['role' => $role, 'person_id' => Person::factory()->create()->id]);
        $u->assignRole($role);
        if ($permissions !== null) {
            $u->givePermissionTo($permissions);
        }

        return $u;
    }

    public function test_a_limited_user_manager_cannot_hand_out_the_admin_role(): void
    {
        // a custom role that may manage users but nothing else
        $helper = Role::create(['name' => 'user_helper', 'guard_name' => 'web']);
        $helper->givePermissionTo(['user.view', 'user.manage']);
        $limited = $this->userWith('user_helper');
        $victim = $this->userWith('tenant');

        $api = $this->actingAs($limited, 'sanctum');
        $api->putJson("/api/users/{$victim->id}", ['roles' => ['admin']])->assertStatus(422)->assertJsonValidationErrors('roles');
        $api->postJson('/api/users', ['name_bn' => 'নতুন', 'mobile' => '01712340000', 'email' => 'n@example.com', 'password' => 'Secret123!', 'roles' => ['admin']])->assertStatus(422);
        $this->assertFalse($victim->fresh()->hasRole('admin'));

        // a role inside their own reach is fine
        $helper->givePermissionTo('building.view');
        $api->putJson("/api/users/{$victim->id}", ['roles' => ['tenant']])->assertOk();
    }

    public function test_a_limited_user_manager_cannot_touch_someone_with_more_power(): void
    {
        $helper = Role::create(['name' => 'user_helper', 'guard_name' => 'web']);
        $helper->givePermissionTo(['user.view', 'user.manage']);
        $limited = $this->userWith('user_helper');
        $admin = $this->userWith('admin');

        $api = $this->actingAs($limited, 'sanctum');
        $api->putJson("/api/users/{$admin->id}", ['status' => 'INACTIVE'])->assertStatus(422)->assertJsonValidationErrors('user');
        $api->postJson("/api/users/{$admin->id}/reset-password", ['password' => 'Takeover123!'])->assertStatus(422);
        $this->assertSame('ACTIVE', $admin->fresh()->status);
    }

    public function test_an_admin_can_still_manage_other_admins_and_roles_within_their_reach(): void
    {
        $a = $this->userWith('admin');
        $b = $this->userWith('admin');

        $this->actingAs($a, 'sanctum')->putJson("/api/users/{$b->id}", ['status' => 'INACTIVE'])->assertOk();
        $this->actingAs($a, 'sanctum')->postJson("/api/users/{$b->id}/reset-password", ['password' => 'NewSecret123!'])->assertOk();
    }

    public function test_a_role_manager_cannot_give_a_role_permissions_they_lack_themselves(): void
    {
        $r = Role::create(['name' => 'role_helper', 'guard_name' => 'web']);
        $r->givePermissionTo(['user.view', 'role.manage', 'building.view']);
        $helper = $this->userWith('role_helper');
        Role::create(['name' => 'parking_guard', 'guard_name' => 'web']);

        $api = $this->actingAs($helper, 'sanctum');
        $api->putJson('/api/roles/parking_guard/permissions', ['permissions' => ['expense.approve']])->assertStatus(422)->assertJsonValidationErrors('permissions');
        $api->putJson('/api/roles/parking_guard/permissions', ['permissions' => ['building.view']])->assertOk();
        $api->postJson('/api/roles', ['name' => 'sneaky', 'permissions' => ['payment.approve']])->assertStatus(422);
    }

    public function test_an_unknown_role_filter_is_an_empty_list_not_an_error(): void
    {
        $admin = $this->userWith('admin');

        $this->actingAs($admin, 'sanctum')->getJson('/api/users?role=no_such_role')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($admin, 'sanctum')->getJson('/api/users?role=admin')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_page_size_is_capped(): void
    {
        $admin = $this->userWith('admin');

        $this->assertSame(100, $this->actingAs($admin, 'sanctum')->getJson('/api/users?per_page=100000')->assertOk()->json('meta.per_page'));
    }

    public function test_the_name_and_mobile_of_a_login_can_be_corrected(): void
    {
        $admin = $this->userWith('admin');
        $target = $this->userWith('committee_member');
        $other = $this->userWith('committee_member');
        $api = $this->actingAs($admin, 'sanctum');

        $api->putJson("/api/users/{$target->id}", ['name_bn' => 'সংশোধিত নাম', 'mobile' => '01755550000'])->assertOk()
            ->assertJsonPath('name', 'সংশোধিত নাম')->assertJsonPath('mobile', '01755550000');
        $this->assertSame('01755550000', $target->person->fresh()->mobile);

        $api->putJson("/api/users/{$target->id}", ['mobile' => $other->mobile])->assertStatus(422)->assertJsonValidationErrors('mobile');
        $api->putJson("/api/users/{$target->id}", ['mobile' => 'abc'])->assertStatus(422);
    }

    public function test_every_permission_in_the_catalog_has_a_readable_group_and_label(): void
    {
        $this->seed(\Database\Seeders\OperationsPermissionSeeder::class);
        $admin = $this->userWith('admin');

        $catalog = $this->actingAs($admin, 'sanctum')->getJson('/api/roles')->assertOk()->json('catalog');
        foreach ($catalog as $g) {
            $this->assertNotSame($g['module'], $g['label_en'], "group {$g['module']} shows its raw name");
            foreach ($g['permissions'] as $p) {
                $this->assertNotSame(explode('.', $p['name'])[1], $p['label_en'], "{$p['name']} shows its raw action name");
            }
        }
    }
}
