<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Parcel;
use App\Models\Reservation;
use App\Models\Route;
use App\Models\Departure;

class CompanyController extends Controller
{
    // Liste de toutes les compagnies (Super Admin)
    public function index()
    {
        return response()->json(Company::withCount(['stations', 'buses'])->latest()->get());
    }

    /**
     * Récupérer toutes les gares appartenant à une compagnie spécifique
     * Route : GET /api/companies/{company}/stations
     */
    public function getStations(Company $company)
    {
        // On récupère uniquement les gares triées par nom
        $stations = $company->stations()
            ->select('id', 'name', 'city', 'address', 'phone', 'company_id')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($stations);
    }

    // Création d'une compagnie + Création automatique du Company Admin + Gestion des Médias
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Informations Compagnie
            'company_name' => 'required|string|max:255|unique:companies,name',
            'company_phone' => 'required|string|max:50',
            'company_email' => 'nullable|email|max:255',

            // Administrateur Principal
            'admin_nom' => 'required|string|max:255',
            'admin_prenoms' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_phone' => 'required|string|max:50|unique:users,phone',
            'admin_password' => 'required|string|min:8',

            // Fichiers Médias
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'images' => 'nullable|array|max:4',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'company_name.unique' => 'Une compagnie avec ce nom existe déjà.',
            'admin_email.unique' => 'Cet e-mail est déjà utilisé.',
            'admin_phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'logo.required' => 'Le logo de la compagnie est obligatoire.',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            // 1. Sauvegarde du Logo dans storage/app/public/companies/logos
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('companies/logos', 'public');
                $logoPath = asset(Storage::url($path));
            }

            // 2. Sauvegarde des photos de galerie dans storage/app/public/companies/gallery
            $galleryPaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $path = $file->store('companies/gallery', 'public');
                    $galleryPaths[] = Storage::url($path);
                }
            }

            // 3. Création de la compagnie
            $company = Company::create([
                'name' => $validated['company_name'],
                'phone' => $validated['company_phone'],
                'email' => $validated['company_email'] ?? null,
                'logo' => $logoPath,
                'images' => count($galleryPaths) > 0 ? json_encode($galleryPaths) : null,
                'is_active' => true,
            ]);

            // 4. Création de l'administrateur principal de la compagnie
            $adminFullName = trim($validated['admin_nom'] . ' ' . $validated['admin_prenoms']);

            $admin = User::create([
                'name' => $adminFullName,
                'email' => $validated['admin_email'],
                'phone' => $validated['admin_phone'],
                'password' => Hash::make($validated['admin_password']),
                'company_id' => $company->id,
            ]);

            $admin->assignRole('company_admin');

            return response()->json([
                'message' => 'Compagnie et compte administrateur créés avec succès !',
                'company' => $company,
                'admin' => $admin
            ], 201);
        });
    }

    public function show(Company $company)
    {
        // On récupère la compagnie avec son administrateur principal (rôle company_admin)
        $admin = $company->users()->role('company_admin')->first();

        return response()->json([
            'id' => $company->id,
            'name' => $company->name,
            'phone' => $company->phone,
            'email' => $company->email,
            'logo' => $company->logo,
            'images' => $company->images,
            'is_active' => $company->is_active,
            'admin' => $admin ? [
                'id' => $admin->id,
                'nom' => explode(' ', $admin->name)[0] ?? $admin->name,
                'prenoms' => implode(' ', array_slice(explode(' ', $admin->name), 1)) ?? '',
                'email' => $admin->email,
                'phone' => $admin->phone,
            ] : null
        ], 200);
    }

    // Activer / Désactiver une compagnie (Blocage d'accès)
    public function toggleStatus(Company $company)
    {
        $company->update(['is_active' => !$company->is_active]);

        return response()->json([
            'message' => 'Statut de la compagnie mis à jour avec succès.',
            'is_active' => $company->is_active
        ]);
    }

    // Suppression d'une compagnie
    public function destroy(Company $company)
    {
        return DB::transaction(function () use ($company) {
            // 1. Suppression du logo physique
            if ($company->logo) {
                $relativeLogoPath = str_replace('/storage/', '', $company->logo);
                Storage::disk('public')->delete($relativeLogoPath);
            }

            // 2. Suppression des images de galerie physiques
            if ($company->images) {
                $images = json_decode($company->images, true);
                if (is_array($images)) {
                    foreach ($images as $imageUrl) {
                        $relativeImagePath = str_replace('/storage/', '', $imageUrl);
                        Storage::disk('public')->delete($relativeImagePath);
                    }
                }
            }

            // 3. Suppression des utilisateurs
            $company->users()->delete();

            // 4. Suppression finale
            $company->delete();

            return response()->json([
                'message' => 'La compagnie ainsi que tous ses comptes et fichiers ont été supprimés avec succès.'
            ], 200);
        });
    }

    /**
     * Mise à jour d'une compagnie et de ses médias
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255|unique:companies,name,' . $company->id,
            'company_phone' => 'required|string|max:50',
            'company_email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'images' => 'nullable|array|max:4',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'company_name.unique' => 'Une compagnie avec ce nom existe déjà.',
            'logo.image' => 'Le fichier transmis pour le logo doit être une image.',
        ]);

        return DB::transaction(function () use ($request, $validated, $company) {
            $dataToUpdate = [
                'name' => $validated['company_name'],
                'phone' => $validated['company_phone'],
                'email' => $validated['company_email'] ?? null,
            ];

            if ($request->hasFile('logo')) {
                if ($company->logo) {
                    $oldLogoPath = str_replace('/storage/', '', $company->logo);
                    Storage::disk('public')->delete($oldLogoPath);
                }

                $path = $request->file('logo')->store('companies/logos', 'public');
                $dataToUpdate['logo'] = Storage::url($path);
            }

            if ($request->hasFile('images')) {
                if ($company->images) {
                    $oldImages = json_decode($company->images, true);
                    if (is_array($oldImages)) {
                        foreach ($oldImages as $oldImageUrl) {
                            $oldPath = str_replace('/storage/', '', $oldImageUrl);
                            Storage::disk('public')->delete($oldPath);
                        }
                    }
                }

                $galleryPaths = [];
                foreach ($request->file('images') as $file) {
                    $path = $file->store('companies/gallery', 'public');
                    $galleryPaths[] = Storage::url($path);
                }
                $dataToUpdate['images'] = json_encode($galleryPaths);
            }

            $company->update($dataToUpdate);

            return response()->json([
                'message' => 'Compagnie mise à jour avec succès !',
                'company' => $company->fresh()
            ], 200);
        });
    }

    public function show360(Company $company)
    {
        // 1. Informations de base + Relations directes
        $companyData = $company->load([
            'stations',                 // Toutes ses gares
            'users',                    // Tout le personnel/staff lié à la compagnie
            'buses',                    // Tous ses véhicules (cars/bus)
        ]);

        // 2. Lignes / Trajets de la compagnie
        $routes = Route::where('company_id', $company->id)
            ->with(['departureStation', 'arrivalStation'])
            ->get();

        // 3. Départs / Voyages de la compagnie (via le modèle Departure)
        $trips = Departure::where('company_id', $company->id)
            ->with(['route', 'bus'])
            ->latest()
            ->limit(100)
            ->get();

        // 4. Toutes les expéditions de colis (avec leurs gares)
        $parcels = Parcel::where('company_id', $company->id)
            ->with(['departureStation', 'arrivalStation'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Statistiques des statuts des colis
        $parcelsByStatus = $parcels->groupBy('status')->map(fn($group) => $group->count());

        // 5. Revenus mensuels des réservations de billets (12 derniers mois)
        $ticketRevenues = DB::table('reservations')
            ->where('company_id', $company->id)
            ->where('payment_status', 'PAID')
            ->where('created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw("DATE_TRUNC('month', created_at) as month"),
                DB::raw("SUM(amount) as total_revenue"),
                DB::raw("COUNT(id) as total_tickets")
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // 6. Revenus mensuels des expéditions de colis (12 derniers mois)
        $parcelRevenues = DB::table('parcels')
            ->where('company_id', $company->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw("DATE_TRUNC('month', created_at) as month"),
                DB::raw("SUM(shipping_cost) as total_revenue"),
                DB::raw("COUNT(id) as total_parcels")
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'phone' => $company->phone,
                'email' => $company->email,
                'logo' => $company->logo,
                'images' => $company->images ? json_decode($company->images) : [],
                'is_active' => $company->is_active,
                'created_at' => $company->created_at,
            ],
            'counts' => [
                'stations' => $company->stations->count(),
                'staff'    => $company->users->count(),
                'buses'    => $company->buses->count(),
                'routes'   => $routes->count(),
                'trips'    => $trips->count(),
                'parcels'  => $parcels->count(),
            ],
            'stations'          => $company->stations,
            'staff'             => $company->users,
            'vehicles'          => $company->buses,
            'routes'            => $routes,
            'trips'             => $trips,
            'parcels'           => [
                'summary' => $parcelsByStatus,
                'list'    => $parcels,
            ],
            'analytics'         => [
                'ticket_revenues_monthly' => $ticketRevenues,
                'parcel_revenues_monthly' => $parcelRevenues,
            ]
        ], 200);
    }
}
