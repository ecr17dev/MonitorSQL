<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlToastTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::factory()->create([
            'key' => 'connections.create',
            'name' => 'connections create',
        ]);

        $role = Role::factory()->create([
            'key' => 'admin',
            'name' => 'Admin',
        ]);
        $role->permissions()->sync([$permission->id]);

        $this->admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->sync([$role->id]);
    }

    public function test_store_update_and_delete_user_flash_toast(): void
    {
        $storeResponse = $this->actingAs($this->admin)
            ->from(route('admin.access-control'))
            ->post(route('admin.users.store'), [
                'name' => 'Nuevo Usuario',
                'email' => 'nuevo.usuario@example.com',
                'password' => 'password1234',
            ]);

        $storeResponse
            ->assertRedirect(route('admin.access-control'))
            ->assertSessionHas('flash.toast.type', 'success');

        $user = User::query()->where('email', 'nuevo.usuario@example.com')->firstOrFail();

        $updateResponse = $this->actingAs($this->admin)
            ->from(route('admin.access-control'))
            ->put(route('admin.users.update', $user), [
                'name' => 'Usuario Actualizado',
                'email' => 'nuevo.usuario@example.com',
                'password' => '',
            ]);

        $updateResponse
            ->assertRedirect(route('admin.access-control'))
            ->assertSessionHas('flash.toast.type', 'success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Usuario Actualizado',
        ]);

        $deleteResponse = $this->actingAs($this->admin)
            ->from(route('admin.access-control'))
            ->delete(route('admin.users.destroy', $user));

        $deleteResponse
            ->assertRedirect(route('admin.access-control'))
            ->assertSessionHas('flash.toast.type', 'success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_store_update_and_delete_role_flash_toast(): void
    {
        $storeResponse = $this->actingAs($this->admin)
            ->from(route('admin.access-control'))
            ->post(route('admin.roles.store'), [
                'name' => 'Operaciones',
                'key' => 'operations',
            ]);

        $storeResponse
            ->assertRedirect(route('admin.access-control'))
            ->assertSessionHas('flash.toast.type', 'success');

        $role = Role::query()->where('key', 'operations')->firstOrFail();

        $updateResponse = $this->actingAs($this->admin)
            ->from(route('admin.access-control'))
            ->put(route('admin.roles.update', $role), [
                'name' => 'Operaciones Actualizado',
                'key' => 'operations',
            ]);

        $updateResponse
            ->assertRedirect(route('admin.access-control'))
            ->assertSessionHas('flash.toast.type', 'success');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Operaciones Actualizado',
        ]);

        $deleteResponse = $this->actingAs($this->admin)
            ->from(route('admin.access-control'))
            ->delete(route('admin.roles.destroy', $role));

        $deleteResponse
            ->assertRedirect(route('admin.access-control'))
            ->assertSessionHas('flash.toast.type', 'success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
