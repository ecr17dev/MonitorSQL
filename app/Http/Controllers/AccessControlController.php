<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessControlController extends Controller
{
    public function __invoke(): Response
    {
        $users = User::query()
            ->with(['roles:id,name,key'])
            ->latest()
            ->paginate(15)
            ->through(function (User $user): array {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->toDateTimeString(),
                    'roles' => $user->roles->map(fn (Role $role): array => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'key' => $role->key,
                    ])->all(),
                ];
            });

        $roles = Role::query()
            ->withCount('users')
            ->with(['permissions:id,name,key'])
            ->orderBy('name')
            ->get()
            ->map(function (Role $role): array {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'key' => $role->key,
                    'users_count' => $role->users_count,
                    'permissions' => $role->permissions->map(fn (Permission $permission): array => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'key' => $permission->key,
                    ])->all(),
                ];
            })
            ->values();

        $permissions = Permission::query()
            ->orderBy('key')
            ->get(['id', 'name', 'key']);

        return Inertia::render('admin/AccessControl', [
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::create($data);

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'message' => __('Usuario creado.')],
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'message' => __('Usuario actualizado.')],
        ]);
    }

    public function destroyUser(User $user): RedirectResponse
    {
        $user->delete();

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'message' => __('Usuario eliminado.')],
        ]);
    }

    public function syncUserRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $user->roles()->sync($data['role_ids'] ?? []);

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'message' => __('Roles actualizados.')],
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'unique:roles,key'],
        ]);

        Role::create($data);

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'message' => __('Rol creado.')],
        ]);
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'unique:roles,key,'.$role->id],
        ]);

        $role->update($data);

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'message' => __('Rol actualizado.')],
        ]);
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        $role->delete();

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'message' => __('Rol eliminado.')],
        ]);
    }

    public function syncRolePermissions(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permission_ids'] ?? []);

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'message' => __('Permisos actualizados.')],
        ]);
    }
}
