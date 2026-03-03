<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // 🔄 Reset cache permission (WAJIB)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ✅ Pastikan role super_admin ADA
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        // 👤 Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'aldiwahyudi1223@gmail.com'],
            [
                'name' => 'Aldi Wahyudi',
                'password' => Hash::make('@Komando1223'),
                'email_verified_at' => now(),
            ]
        );

        // 🛡 Assign role (AMAN)
        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole($role);
        }

        // 📢 Info ke terminal
        $this->command->info('✅ Admin user created / found');
        $this->command->info('✅ Role super_admin assigned');
        $this->command->info('Email: aldiwahyudi1223@gmail.com');
    }
}

# Generate policies untuk semua resources
// php artisan shield:generate --all