<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    // Liste du personnel de la compagnie
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        if (!$companyId) {
            return response()->json([
                'message' => 'Aucune compagnie associée à cet administrateur.'
            ], 400);
        }

        return response()->json(
            User::where('company_id', $companyId)->with('roles')->latest()->get()
        );
    }

    // Récupérer un membre spécifique du personnel
    public function show(Request $request, User $user)
    {
        $admin = $request->user();

        if ($user->company_id !== $admin->company_id) {
            return response()->json([
                'message' => 'Action non autorisée sur cet utilisateur.'
            ], 403);
        }

        return response()->json($user->load('roles'));
    }

    // Enregistrer un membre du personnel
    public function store(Request $request)
    {
        $admin = $request->user();

        if (!$admin->company_id) {
            return response()->json([
                'message' => 'Vous devez appartenir à une compagnie pour ajouter du personnel.'
            ], 403);
        }

        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255',
            'prenoms' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'number' => 'sometimes|required|string|unique:users,phone',
            'phone' => 'sometimes|required|string|unique:users,phone',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:company_admin,cashier,station_manager,driver',
        ], [
            'email.unique' => 'Cet e-mail est déjà utilisé.',
            'number.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'role.in' => 'Le rôle sélectionné n\'est pas valide.',
        ]);

        return DB::transaction(function () use ($validated, $admin) {
            if (isset($validated['nom']) && isset($validated['prenoms'])) {
                $fullName = trim($validated['nom'] . ' ' . $validated['prenoms']);
            } else {
                $fullName = $validated['name'];
            }

            $phone = $validated['number'] ?? $validated['phone'];

            $user = User::create([
                'name' => $fullName,
                'email' => $validated['email'],
                'phone' => $phone,
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'company_id' => $admin->company_id,
            ]);

            $user->assignRole($validated['role']);

            return response()->json([
                'message' => 'Membre du personnel créé avec succès.',
                'user' => $user->load('roles')
            ], 201);
        });
    }

    // Mettre à jour les informations d'un membre du personnel
    public function update(Request $request, User $user)
    {
        $admin = $request->user();

        // Vérification d'appartenance à la même compagnie
        if ($user->company_id !== $admin->company_id) {
            return response()->json([
                'message' => 'Action non autorisée sur cet utilisateur.'
            ], 403);
        }

        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255',
            'prenoms' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'number' => ['sometimes', 'required', 'string', Rule::unique('users', 'phone')->ignore($user->id)],
            'phone' => ['sometimes', 'required', 'string', Rule::unique('users', 'phone')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|in:company_admin,cashier,station_manager,driver',
        ], [
            'email.unique' => 'Cet e-mail est déjà utilisé par un autre utilisateur.',
            'number.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'role.in' => 'Le rôle sélectionné n\'est pas valide.',
        ]);

        return DB::transaction(function () use ($validated, $user) {
            if (isset($validated['nom']) && isset($validated['prenoms'])) {
                $fullName = trim($validated['nom'] . ' ' . $validated['prenoms']);
            } else {
                $fullName = $validated['name'] ?? $user->name;
            }

            $phone = $validated['number'] ?? $validated['phone'] ?? $user->phone;

            $updateData = [
                'name' => $fullName,
                'email' => $validated['email'],
                'phone' => $phone,
                'role' => $validated['role'],
            ];

            // Mettre à jour le mot de passe s'il a été saisi
            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);

            // Synchronisation du rôle Spatie
            $user->syncRoles([$validated['role']]);

            return response()->json([
                'message' => 'Membre du personnel mis à jour avec succès.',
                'user' => $user->fresh()->load('roles')
            ], 200);
        });
    }

    // Modifier uniquement le rôle
    public function updateRole(Request $request, User $user)
    {
        $admin = $request->user();

        if ($user->company_id !== $admin->company_id) {
            return response()->json([
                'message' => 'Action non autorisée sur cet utilisateur.'
            ], 403);
        }

        $validated = $request->validate([
            'role' => 'required|string|in:company_admin,cashier,station_manager,driver'
        ]);

        $user->syncRoles([$validated['role']]);
        $user->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'Rôle mis à jour avec succès.',
            'user' => $user->load('roles')
        ]);
    }

    // Supprimer un membre du personnel
    public function destroy(Request $request, User $user)
    {
        $admin = $request->user();

        if ($user->company_id !== $admin->company_id) {
            return response()->json([
                'message' => 'Action non autorisée sur cet utilisateur.'
            ], 403);
        }

        if ($user->id === $admin->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.'
            ], 422);
        }

        return DB::transaction(function () use ($user) {
            $user->syncRoles([]);
            $user->delete();

            return response()->json([
                'message' => 'Membre du personnel supprimé avec succès.'
            ], 200);
        });
    }
}
