<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Réinitialiser le cache des rôles et permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Liste des permissions par module
        $permissions = [
            // Compagnie & Gares
            'manage-companies',
            'manage-stations',

            // Flotte & Lignes
            'manage-vehicles',
            'view-vehicles',
            'manage-routes',
            'view-routes',

            // Trajets
            'manage-trips',
            'view-trips',

            // Billets (Guichet & Vente)
            'create-tickets',
            'view-tickets',
            'update-tickets',
            'cancel-tickets',

            // Colis (Expédition & Suivi)
            'create-parcels',
            'view-parcels',
            'update-parcels',

            // Paiements & Dashboard
            'process-payments',
            'view-payments',
            'view-dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // 2. Création et attribution des Rôles

        // Super Admin (Accès total système)
        $superAdmin = Role::findOrCreate('super-admin', 'web');

        // Admin Compagnie (Gestion complète de sa compagnie)
        $companyAdmin = Role::findOrCreate('admin', 'web');
        $companyAdmin->givePermissionTo([
            'manage-stations',
            'manage-vehicles', 'view-vehicles',
            'manage-routes', 'view-routes',
            'manage-trips', 'view-trips',
            'view-tickets', 'cancel-tickets',
            'view-parcels',
            'view-payments',
            'view-dashboard',
        ]);

        // Chef de Gare (Gestion opérationnelle locale)
        $stationManager = Role::findOrCreate('station-manager', 'web');
        $stationManager->givePermissionTo([
            'view-vehicles',
            'view-routes',
            'manage-trips', 'view-trips',
            'create-tickets', 'view-tickets', 'cancel-tickets',
            'create-parcels', 'view-parcels', 'update-parcels',
            'process-payments', 'view-payments',
            'view-dashboard',
        ]);

        // Guichetier (Vente de billets et réception des colis)
        $ticketAgent = Role::findOrCreate('ticket-agent', 'web');
        $ticketAgent->givePermissionTo([
            'view-trips',
            'create-tickets', 'view-tickets',
            'create-parcels', 'view-parcels', 'update-parcels',
            'process-payments',
        ]);

        // Chauffeur (Consultation des trajets et validation d'embarquement)
        $driver = Role::findOrCreate('driver', 'web');
        $driver->givePermissionTo([
            'view-trips',
            'view-tickets',
            'update-parcels',
        ]);

        // Client / Passager
        $passenger = Role::findOrCreate('passenger', 'web');
        $passenger->givePermissionTo([
            'view-routes',
            'view-trips',
            'create-tickets',
            'view-tickets',
            'create-parcels',
            'view-parcels',
        ]);
    }
}
