<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorSqlNavigationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_access_control_and_backups_pages_require_admin_scope()
    {
        $admin = $this->createUserWithPermissions(['connections.create']);

        $this->actingAs($admin)
            ->get('/admin/access-control')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/backups')
            ->assertOk();

        $basicUser = User::factory()->create();

        $this->actingAs($basicUser)
            ->get('/admin/access-control')
            ->assertStatus(403);

        $this->actingAs($basicUser)
            ->get('/backups')
            ->assertStatus(403);
    }

    public function test_export_queue_page_requires_export_permission()
    {
        $authorizedUser = $this->createUserWithPermissions(['queries.export']);

        $this->actingAs($authorizedUser)
            ->get('/exports/queue')
            ->assertOk();

        $unauthorizedUser = User::factory()->create();

        $this->actingAs($unauthorizedUser)
            ->get('/exports/queue')
            ->assertStatus(403);
    }

    private function createUserWithPermissions(array $permissionKeys): User
    {
        $permissionIds = collect($permissionKeys)
            ->map(function (string $permissionKey): int {
                $permission = Permission::query()->firstOrCreate(
                    ['key' => $permissionKey],
                    ['name' => $permissionKey],
                );

                return $permission->id;
            })
            ->all();

        $role = Role::query()->create([
            'name' => 'Scoped Role',
            'key' => 'scoped-role-'.fake()->randomNumber(4),
        ]);
        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
