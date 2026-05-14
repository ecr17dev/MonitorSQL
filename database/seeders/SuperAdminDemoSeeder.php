<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminDemoSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissionKeys = Permission::query()->pluck('key')->all();

        $superadmin = Role::query()->firstOrCreate(
            ['key' => 'superadmin'],
            ['name' => 'Super Admin'],
        );

        $permissionIds = Permission::query()
            ->whereIn('key', $allPermissionKeys)
            ->pluck('id')
            ->all();

        $superadmin->permissions()->sync($permissionIds);

        $demoPassword = app()->environment('local')
            ? 'demo'
            : fake()->password(16);

        $user = User::factory()->create([
            'name' => 'Demo Superadmin',
            'email' => 'demo@monitorsql.dev',
            'password' => $demoPassword,
        ]);

        $user->roles()->sync([$superadmin->id]);

        $this->command?->info('Superadmin demo created:');
        $this->command?->info('  Email:    demo@monitorsql.dev');
        $this->command?->info('  Password: '.$demoPassword);
        $this->command?->info('  Role:     superadmin ('.count($allPermissionKeys).' permissions)');
    }
}
