<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Parcel;
use App\Models\Payment;
use App\Models\Route;
use App\Models\Station;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Réinitialiser le cache Spatie et charger les Rôles et Permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Création de la Compagnie Principale
        $company = Company::create([
            'name' => 'STC Express Côte d\'Ivoire',
            'slug' => 'stc-express-ci',
            'email' => 'contact@stcexpress.ci',
            'phone' => '+225 2720000000',
            'address' => 'Adjamé Gare Central, Abidjan',
        ]);

        // 3. Création des Gares principales
        $stationAbidjan = Station::create([
            'company_id' => $company->id,
            'name' => 'Gare Adjamé Renault',
            'city' => 'Abidjan',
            'address' => 'Adjamé, Boulevard Nangui Abrogoua',
            'phone' => '+225 0701010101',
        ]);

        $stationBouake = Station::create([
            'company_id' => $company->id,
            'name' => 'Gare Centrale Bouaké',
            'city' => 'Bouaké',
            'address' => 'Quartier Commerce, Bouaké',
            'phone' => '+225 0702020202',
        ]);

        // 4. Création des Utilisateurs de démonstration par Rôle
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@system.com',
            'phone' => '+225 0700000000',
            'password' => Hash::make('password'),
        ]);
        $superAdmin->assignRole('super-admin');

        $companyAdmin = User::create([
            'name' => 'Kouassi Admin',
            'email' => 'admin@stcexpress.ci',
            'phone' => '+225 0700000001',
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
        $companyAdmin->assignRole('admin');

        $manager = User::create([
            'name' => 'Yao Chef de Gare',
            'email' => 'manager.adjame@stcexpress.ci',
            'phone' => '+225 0700000002',
            'company_id' => $company->id,
            'station_id' => $stationAbidjan->id,
            'password' => Hash::make('password'),
        ]);
        $manager->assignRole('station-manager');

        $agent = User::create([
            'name' => 'Konan Guichetier',
            'email' => 'guichet1@stcexpress.ci',
            'phone' => '+225 0700000003',
            'company_id' => $company->id,
            'station_id' => $stationAbidjan->id,
            'password' => Hash::make('password'),
        ]);
        $agent->assignRole('ticket-agent');

        $driver = User::create([
            'name' => 'Bakayoko Chauffeur',
            'email' => 'chauffeur1@stcexpress.ci',
            'phone' => '+225 0700000004',
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
        $driver->assignRole('driver');

        // 5. Création de la flotte de Véhicules
        $vehicles = Vehicle::factory(5)->create(['company_id' => $company->id]);

        // 6. Création de la ligne Abidjan -> Bouaké
        $route = Route::create([
            'company_id' => $company->id,
            'departure_station_id' => $stationAbidjan->id,
            'arrival_station_id' => $stationBouake->id,
            'distance_km' => 350,
            'estimated_duration_minutes' => 300,
            'base_price' => 6000,
        ]);

        // 7. Génération des Trajets, Billets, Colis et Paiements
        Trip::factory(4)->create([
            'company_id' => $company->id,
            'route_id' => $route->id,
            'vehicle_id' => $vehicles->random()->id,
        ])->each(function ($trip) use ($agent) {

            // Billets pour chaque trajet
            Ticket::factory(5)->create([
                'trip_id' => $trip->id,
                'user_id' => $agent->id,
                'price' => $trip->price ?? 6000,
            ])->each(function ($ticket) use ($agent) {

                // Paiement associé à chaque billet
                Payment::create([
                    'paymentable_type' => Ticket::class,
                    'paymentable_id' => $ticket->id,
                    'user_id' => $agent->id,
                    'amount' => $ticket->price,
                    'payment_method' => 'mobile_money',
                    'provider' => 'Wave',
                    'transaction_reference' => 'PAY-' . strtoupper(Str::random(8)),
                    'status' => 'completed',
                ]);
            });
        });

        // 8. Colis expédiés
        Parcel::factory(10)->create([
            'company_id' => $company->id,
            'departure_station_id' => $stationAbidjan->id,
            'destination_station_id' => $stationBouake->id,
            'sender_id' => $agent->id,
        ])->each(function ($parcel) use ($agent) {

            Payment::create([
                'paymentable_type' => Parcel::class,
                'paymentable_id' => $parcel->id,
                'user_id' => $agent->id,
                'amount' => $parcel->shipping_cost,
                'payment_method' => 'cash',
                'provider' => null,
                'transaction_reference' => 'PAY-' . strtoupper(Str::random(8)),
                'status' => 'completed',
            ]);
        });
    }
}
