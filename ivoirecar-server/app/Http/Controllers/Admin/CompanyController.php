<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    /**
     * Liste toutes les compagnies enregistrées.
     */
    public function index(): JsonResponse
    {
        $companies = Company::withCount(['stations', 'vehicles', 'employees'])->get();

        return response()->json([
            'data' => $companies,
        ]);
    }

    /**
     * Création d'une nouvelle compagnie et de son Admin principal.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Informations Compagnie
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:companies,email',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:255',
            'commission_rate' => 'nullable|numeric|min:0|max:100',

            // Informations Admin de la Compagnie
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_phone' => 'required|string|max:20|unique:users,phone',
            'admin_password' => 'required|string|min:8',
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Création de la compagnie
            $company = Company::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'] ?? null,
                'commission_rate' => $validated['commission_rate'] ?? 0.00,
                'is_active' => true,
            ]);

            // 2. Création du compte Admin rattaché
            $admin = User::create([
                'company_id' => $company->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'phone' => $validated['admin_phone'],
                'password' => Hash::make($validated['admin_password']),
            ]);

            // 3. Assignation du rôle 'admin'
            $admin->assignRole('admin');

            return response()->json([
                'message' => 'Compagnie et compte Administrateur créés avec succès.',
                'company' => $company->load('employees'),
            ], 201);
        });
    }

    /**
     * Afficher les détails d'une compagnie.
     */
    public function show(Company $company): JsonResponse
    {
        return response()->json([
            'company' => $company->load(['stations', 'vehicles', 'employees']),
        ]);
    }

    /**
     * Activer ou désactiver une compagnie (Statut d'abonnement / accès).
     */
    public function toggleStatus(Company $company): JsonResponse
    {
        $company->update([
            'is_active' => ! $company->is_active,
        ]);

        $status = $company->is_active ? 'activée' : 'désactivée';

        // Si la compagnie est désactivée, déconnecter tous ses utilisateurs actifs
        if (! $company->is_active) {
            $userTokens = $company->employees()->pluck('id');
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userTokens)
                ->delete();
        }

        return response()->json([
            'message' => "La compagnie '{$company->name}' a été {$status} avec succès.",
            'is_active' => $company->is_active,
        ]);
    }
}
