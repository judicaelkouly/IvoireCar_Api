<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleAndUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Réinitialiser le cache des permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Création des 5 rôles principaux
        $roles = [
            'super_admin',
            'company_admin',
            'station_manager',
            'driver',
            'cashier',
            'passenger',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // 3. Création du compte Super Admin initial
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@ivoirecar.ci'],
            [
                'name' => 'Super Administrateur',
                'phone' => '+2250700000000',
                'password' => Hash::make('Password123!'),
            ]
        );

        // Attribution du rôle super_admin
        $superAdmin->assignRole('super_admin');
    }
}
