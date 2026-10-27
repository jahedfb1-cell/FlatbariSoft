<?php

namespace Tests\Feature;

use App\Models\CommunityHall;
use App\Models\Flat;
use App\Models\Person;
use App\Models\User;
use App\Services\HallBookingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RoleSeeder::class);
        foreach ([
            \Database\Seeders\FlatMasterPermissionSeeder::class, \Database\Seeders\DocumentPermissionSeeder::class,
            \Database\Seeders\StaffPermissionSeeder::class, MoneyCorePermissionSeeder::class,
            \Database\Seeders\GovernancePermissionSeeder::class, \Database\Seeders\CommunicationPermissionSeeder::class,
        ] as $seeder) {
            $this->seed($seeder);
        }
        $this->seed(CommunityHallSeeder::class);
        $this->seed(RoleCatalogSeeder::class);
    }

    private function userWithRole(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge(['role' => $role], $attrs));
        $user->assignRole($role);

        return $user;
    }

    private function newUserBody(array $over = []): array
    {
        return array_merge([
            'name_bn' => 'কামরুল ইসলাম', 'mobile' => '01712345678', 'email' => 'kamrul@example.com',
            'password' => 'Secret#1234', 'roles' => ['committee_secretary'],
        ], $over);
    }

    // ---- shipped roles

    public function test_committee_roles_are_seeded_with_their_starting_permissions(): void
    {
        foreach (['committee_president', 'committee_secretary', 'committee_treasurer', 'committee_member'] as $name) {
            $this->assertNotNull(Role::where('name', $name)->first(), $name);
        }
        $secretary = Role::findByName('committee_secretary', 'web');
        $treasurer = Role::findByName('committee_treasurer', 'web');

        // the secretary approves hall bookings but does not handle money; the treasurer is the reverse
        $this->assertTrue($secretary->hasPermissionTo('hall.approve'));
        $this->assertFalse($secretary->hasPermissionTo('hall.payments'));
        $this->assertTrue($treasurer->hasPermissionTo('hall.payments'));
        $this->assertFalse($treasurer->hasPermissionTo('hall.approve'));
        // maker-checker: the treasurer records payments, only the president/admin approves them
        $this->assertTrue($treasurer->hasPermissionTo('payment.create'));
        $this->assertFalse($treasurer->hasPermissionTo('payment.approve'));
        $this->assertTrue(Role::findByName('committee_president', 'web')->hasPermissionTo('payment.approve'));
    }

    public function test_reseeding_never_overwrites_permissions_an_admin_changed(): void
    {
        $role = Role::findByName('committee_member', 'web');
        $role->revokePermissionTo('hall.view');

        $this->seed(RoleCatalogSeeder::class);

        $this->assertFalse(Role::findByName('committee_member', 'web')->hasPermissionTo('hall.view'));
    }

    public function test_committee_roles_work_end_to_end_on_the_hall(): void
    {
        $secretary = $this->userWithRole('committee_secretary');
        $treasurer = $this->userWithRole('committee_treasurer');
        $member = $this->userWithRole('committee_member');

        // a real, pending request to act on (route binding answers 404 before the permission check otherwise)
        CommunityHall::first()->rates()->where('slot', 'DAY')->update(['resident_rate' => 100, 'outsider_rate' => 200]);
        $booking = app(HallBookingService::class)->create([
            'customer_name' => 'রহিম', 'customer_mobile' => '01711111111', 'event_type' => 'OTHER',
            'booking_date' => now()->addDays(10)->toDateString(), 'slot' => 'DAY',
        ], $secretary->id);
        $id = $booking->id;

        $this->actingAs($member, 'sanctum')->getJson('/api/hall/bookings')->assertOk();
        $this->actingAs($member, 'sanctum')->postJson('/api/hall/bookings', [])->assertForbidden();
        $this->actingAs($member, 'sanctum')->postJson("/api/hall/bookings/{$id}/approve")->assertForbidden();
        $this->actingAs($treasurer, 'sanctum')->postJson("/api/hall/bookings/{$id}/approve")->assertForbidden();
        $this->actingAs($treasurer, 'sanctum')->putJson('/api/hall', [])->assertForbidden();
        $this->actingAs($secretary, 'sanctum')->postJson("/api/hall/bookings/{$id}/payments", [])->assertForbidden();

        // the secretary approves; only then can the treasurer take the money
        $this->actingAs($secretary, 'sanctum')->postJson("/api/hall/bookings/{$id}/approve")->assertOk()->assertJsonPath('status', 'CONFIRMED');
        $this->actingAs($treasurer, 'sanctum')->postJson("/api/hall/bookings/{$id}/payments", [
            'type' => 'RENT', 'amount' => 50, 'method' => 'CASH', 'paid_on' => now()->toDateString(),
        ])->assertOk()->assertJsonPath('rent_paid', '50.0000');
    }

    // ---- access

    public function test_only_users_with_the_permission_can_open_the_user_screens(): void
    {
        $this->actingAs($this->userWithRole('manager'), 'sanctum')->getJson('/api/users')->assertForbidden();
        $this->actingAs($this->userWithRole('flat_owner'), 'sanctum')->getJson('/api/roles')->assertForbidden();
        $this->actingAs($this->userWithRole('committee_secretary'), 'sanctum')->postJson('/api/users', $this->newUserBody())->assertForbidden();

        $this->actingAs($this->userWithRole('admin'), 'sanctum')->getJson('/api/users')->assertOk();
        // the president may look, but not change
        $president = $this->userWithRole('committee_president');
        $this->actingAs($president, 'sanctum')->getJson('/api/roles')->assertOk();
        $this->actingAs($president, 'sanctum')->postJson('/api/users', $this->newUserBody())->assertForbidden();
    }

    // ---- creating logins

    public function test_admin_creates_a_committee_login_with_a_person_and_role(): void
    {
        $admin = $this->userWithRole('admin');

        $res = $this->actingAs($admin, 'sanctum')->postJson('/api/users', $this->newUserBody())->assertCreated();

        $res->assertJsonPath('name', 'কামরুল ইসলাম')->assertJsonPath('role', 'committee_secretary');
        $user = User::where('mobile', '01712345678')->first();
        $this->assertTrue($user->hasRole('committee_secretary'));
        $this->assertSame('ACTIVE', $user->status);
        $this->assertNotNull($user->person_id);

        // and that login really works with email + password
        $this->postJson('/api/auth/login', ['email' => 'kamrul@example.com', 'password' => 'Secret#1234'])->assertOk();
    }

    public function test_a_person_can_hold_a_committee_role_and_still_be_a_flat_owner(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', $this->newUserBody(['roles' => ['committee_treasurer', 'flat_owner']]))
            ->assertCreated()->assertJsonPath('role', 'committee_treasurer');

        $user = User::where('mobile', '01712345678')->first();
        $this->assertEqualsCanonicalizing(['committee_treasurer', 'flat_owner'], $user->getRoleNames()->all());
    }

    public function test_super_admin_role_can_never_be_handed_out_here(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', $this->newUserBody(['roles' => ['super_admin']]))
            ->assertStatus(422)->assertJsonValidationErrors('roles');
    }

    public function test_unknown_role_duplicate_mobile_and_weak_password_are_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/users', $this->newUserBody(['roles' => ['nonsense']]))->assertStatus(422)->assertJsonValidationErrors('roles');
        $this->postJson('/api/users', $this->newUserBody(['password' => 'short']))->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/users', $this->newUserBody())->assertCreated();
        $this->postJson('/api/users', $this->newUserBody(['email' => 'other@example.com']))->assertStatus(422)->assertJsonValidationErrors('mobile');
    }

    public function test_an_existing_person_can_be_given_a_login(): void
    {
        $admin = $this->userWithRole('admin');
        $person = Person::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', ['person_id' => $person->id, 'email' => 'p@example.com', 'password' => 'Secret#1234', 'roles' => ['manager']])
            ->assertCreated();

        $this->assertSame($person->mobile, User::where('person_id', $person->id)->first()->mobile);
    }

    // ---- editing logins

    public function test_changing_roles_changes_what_the_user_can_do_and_the_label(): void
    {
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('committee_member');

        $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$user->id}", ['roles' => ['committee_secretary']])
            ->assertOk()->assertJsonPath('role', 'committee_secretary');

        $this->assertTrue($user->fresh()->can('hall.approve'));
        $this->assertFalse($user->fresh()->hasRole('committee_member'));
    }

    public function test_deactivating_a_user_blocks_login_and_revokes_their_tokens(): void
    {
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('manager', ['email' => 'm@example.com', 'password' => 'Secret#1234']);
        $user->createToken('t');

        $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$user->id}", ['status' => 'INACTIVE'])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/auth/login', ['email' => 'm@example.com', 'password' => 'Secret#1234'])->assertStatus(422);
    }

    public function test_you_cannot_lock_yourself_out(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin, 'sanctum');

        $this->putJson("/api/users/{$admin->id}", ['status' => 'INACTIVE'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->putJson("/api/users/{$admin->id}", ['roles' => ['manager']])->assertStatus(422)->assertJsonValidationErrors('roles');
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_the_super_admin_account_cannot_be_edited_by_an_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $super = $this->userWithRole('super_admin');
        $this->actingAs($admin, 'sanctum');

        $this->putJson("/api/users/{$super->id}", ['status' => 'INACTIVE'])->assertStatus(422);
        $this->postJson("/api/users/{$super->id}/reset-password", ['password' => 'Hacked#12345'])->assertStatus(422);
    }

    public function test_password_reset_works_and_logs_the_user_out_everywhere(): void
    {
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('manager', ['email' => 'm@example.com']);
        $user->createToken('t');

        $this->actingAs($admin, 'sanctum')->postJson("/api/users/{$user->id}/reset-password", ['password' => 'Brand#New123'])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/auth/login', ['email' => 'm@example.com', 'password' => 'Brand#New123'])->assertOk();
    }

    public function test_user_list_filters_by_role_and_search(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin, 'sanctum')->postJson('/api/users', $this->newUserBody())->assertCreated();
        $this->userWithRole('manager');

        $byRole = $this->getJson('/api/users?role=committee_secretary')->assertOk();
        $this->assertCount(1, $byRole->json('data'));

        $search = $this->getJson('/api/users?q='.urlencode('কামরুল'))->assertOk();
        $this->assertCount(1, $search->json('data'));
    }

    // ---- roles screen

    public function test_roles_list_has_labels_counts_and_a_grouped_permission_catalog(): void
    {
        $admin = $this->userWithRole('admin');
        $this->userWithRole('committee_secretary');

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/roles')->assertOk();

        $secretary = collect($res->json('roles'))->firstWhere('name', 'committee_secretary');
        $this->assertSame('সম্পাদক', $secretary['label_bn']);
        $this->assertSame(1, $secretary['users_count']);
        $this->assertContains('hall.approve', $secretary['permissions']);
        $this->assertTrue(collect($res->json('roles'))->firstWhere('name', 'super_admin')['locked']);
        $this->assertTrue(collect($res->json('roles'))->firstWhere('name', 'flat_owner')['locked']);

        $hallGroup = collect($res->json('catalog'))->firstWhere('module', 'hall');
        $this->assertSame('কমিউনিটি হল', $hallGroup['label_bn']);
    }

    public function test_admin_changes_a_roles_permissions_and_it_takes_effect(): void
    {
        $admin = $this->userWithRole('admin');
        $member = $this->userWithRole('committee_member');
        $this->assertFalse($member->can('notice.manage'));

        $perms = Role::findByName('committee_member', 'web')->permissions->pluck('name')->push('notice.manage')->all();
        $this->actingAs($admin, 'sanctum')->putJson('/api/roles/committee_member/permissions', ['permissions' => $perms])->assertOk();

        $this->assertTrue($member->fresh()->can('notice.manage'));
    }

    public function test_locked_and_resident_roles_cannot_be_edited(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/roles/super_admin/permissions', ['permissions' => ['flat.view']])->assertStatus(422);
        // a permission on flat_owner would open the whole building's data to every resident
        $this->putJson('/api/roles/flat_owner/permissions', ['permissions' => ['flat.view']])->assertStatus(422);
        $this->putJson('/api/roles/tenant/permissions', ['permissions' => ['payment.view']])->assertStatus(422);
    }

    public function test_admin_cannot_remove_their_own_user_and_role_management(): void
    {
        $admin = $this->userWithRole('admin');
        $perms = Role::findByName('admin', 'web')->permissions->pluck('name')->reject(fn ($p) => $p === 'role.manage')->values()->all();

        $this->actingAs($admin, 'sanctum')->putJson('/api/roles/admin/permissions', ['permissions' => $perms])
            ->assertStatus(422)->assertJsonValidationErrors('permissions');
        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('role.manage'));
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')->putJson('/api/roles/committee_member/permissions', ['permissions' => ['made.up']])->assertStatus(422);
    }

    public function test_custom_roles_can_be_created_and_deleted_but_system_roles_cannot(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/roles', ['name' => 'parking_guard', 'permissions' => ['hall.view']])->assertCreated();
        $this->assertTrue(Role::findByName('parking_guard', 'web')->hasPermissionTo('hall.view'));
        $this->postJson('/api/roles', ['name' => 'Bad Name', 'permissions' => []])->assertStatus(422);
        $this->postJson('/api/roles', ['name' => 'parking_guard', 'permissions' => []])->assertStatus(422); // duplicate

        $user = $this->userWithRole('parking_guard');
        $this->deleteJson('/api/roles/parking_guard')->assertStatus(422); // has a user
        $user->removeRole('parking_guard');
        $this->deleteJson('/api/roles/parking_guard')->assertOk();

        $this->deleteJson('/api/roles/manager')->assertStatus(422); // shipped role
    }

    public function test_residents_still_reach_their_own_portal_but_nothing_in_the_office(): void
    {
        $owner = $this->userWithRole('flat_owner');
        $tenant = $this->userWithRole('tenant');
        Flat::factory()->create();

        foreach ([$owner, $tenant] as $resident) {
            $this->actingAs($resident, 'sanctum')->getJson('/api/me/flats')->assertOk();
            $this->actingAs($resident, 'sanctum')->getJson('/api/me/hall/bookings')->assertOk();
            $this->actingAs($resident, 'sanctum')->getJson('/api/users')->assertForbidden();
            $this->actingAs($resident, 'sanctum')->getJson('/api/payments')->assertForbidden();
            $this->actingAs($resident, 'sanctum')->getJson('/api/hall/bookings')->assertForbidden();
        }
    }
}
