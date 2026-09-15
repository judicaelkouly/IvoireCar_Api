<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    /**
     * Liste tous les employés de la compagnie de l'Admin connecté.
     */
    public function index(): JsonResponse
    {
        $companyId = auth()->user()->company_id;

        $staff = User::where('company_id', $companyId)
            ->with(['roles', 'station'])
            ->get();

        return response()->json([
            'data' => $staff,
        ]);
    }

    /**
     * Création d'un sous-agent (Chef de gare, Guichetier, Chauffeur).
     */
    public function store(Request $request): JsonResponse
    {
        $admin = auth()->user();

        // 1. Validation de la requête
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8',
            'role' => [
                'required',
                'string',
                Rule::in(['station-manager', 'ticket-agent', 'driver']), // Restreint aux 3 rôles opérationnels
            ],
            'station_id' => [
                'nullable',
                Rule::requiredIf(in_array($request->role, ['station-manager', 'ticket-agent'])),
                Rule::exists('stations', 'id')->where(function ($query) use ($admin) {
                    return $query->where('company_id', $admin->company_id); // Vérifie que la gare appartient bien à sa compagnie
                }),
            ],
        ]);

        // 2. Création de l'utilisateur avec le company_id de l'Admin
        $agent = User::create([
            'company_id' => $admin->company_id,
            'station_id' => $validated['station_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
        ]);

        // 3. Assignation du rôle Spatie
        $agent->assignRole($validated['role']);

        return response()->json([
            'message' => 'Agent créé avec succès.',
            'agent' => $agent->load(['roles', 'station']),
        ], 201);
    }

    /**
     * Détails d'un employé spécifique.
     */
    public function show(User $user): JsonResponse
    {
        // Sécurité : s'assurer que l'employé appartient bien à la compagnie de l'Admin
        if ($user->company_id !== auth()->user()->company_id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        return response()->json([
            'data' => $user->load(['roles', 'station']),
        ]);
    }
}
