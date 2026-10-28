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
    // Liste du personnel (Filtré par gare si c'est un chef de gare, ou toute la compagnie si c'est l'admin)
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->company_id) {
            return response()->json([
                'message' => 'Aucune compagnie associée à cet utilisateur.'
            ], 400);
        }

        $query = User::where('company_id', $user->company_id)->with(['roles', 'station']);

        // Si l'utilisateur connecté est un chef de gare (station_manager), il ne voit que le personnel de SA gare
        if ($user->hasRole('station_manager')) {
            $query->where('station_id', $user->station_id);
        }
        // L'admin de compagnie peut optionnellement filtrer par gare via un paramètre ?station_id=X dans la requête
        elseif ($request->has('station_id')) {
            $query->where('station_id', $request->station_id);
        }

        return response()->json($query->latest()->get());
    }

    // Récupérer un membre spécifique du personnel
    public function show(Request $request, User $userModel)
    {
        $admin = $request->user();

        if ($userModel->company_id !== $admin->company_id) {
            return response()->json([
                'message' => 'Action non autorisée sur cet utilisateur.'
            ], 403);
        }

        // Si chef de gare, vérifier qu'il appartient à sa gare
        if ($admin->hasRole('station_manager') && $userModel->station_id !== $admin->station_id) {
            return response()->json([
                'message' => 'Accès refusé à ce membre du personnel d\'une autre gare.'
            ], 403);
        }

        return response()->json($userModel->load(['roles', 'station']));
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
            'station_id' => 'nullable|exists:stations,id', // Gare d'affectation
        ], [
            'email.unique' => 'Cet e-mail est déjà utilisé.',
            'number.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'role.in' => 'Le rôle sélectionné n\'est pas valide.',
            'station_id.exists' => 'La gare sélectionnée est invalide.',
        ]);

        return DB::transaction(function () use ($validated, $admin) {
            if (isset($validated['nom']) && isset($validated['prenoms'])) {
                $fullName = trim($validated['nom'] . ' ' . $validated['prenoms']);
            } else {
                $fullName = $validated['name'];
            }

            $phone = $validated['number'] ?? $validated['phone'];

            // Si c'est un chef de gare qui crée, on force sa propre gare par défaut si station_id n'est pas fourni
            $stationId = $validated['station_id'] ?? ($admin->hasRole('station_manager') ? $admin->station_id : null);

            $user = User::create([
                'name' => $fullName,
                'email' => $validated['email'],
                'phone' => $phone,
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'company_id' => $admin->company_id,
                'station_id' => $stationId,
            ]);

            $user->assignRole($validated['role']);

            return response()->json([
                'message' => 'Membre du personnel créé et affecté avec succès.',
                'user' => $user->load(['roles', 'station'])
            ], 201);
        });
    }

    // Mettre à jour les informations d'un membre du personnel
    public function update(Request $request, User $userModel)
    {
        $admin = $request->user();

        if ($userModel->company_id !== $admin->company_id) {
            return response()->json([
                'message' => 'Action non autorisée sur cet utilisateur.'
            ], 403);
        }

        if ($admin->hasRole('station_manager') && $userModel->station_id !== $admin->station_id) {
            return response()->json([
                'message' => 'Action non autorisée sur un utilisateur d\'une autre gare.'
            ], 403);
        }

        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255',
            'prenoms' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($userModel->id)],
            'number' => ['sometimes', 'required', 'string', Rule::unique('users', 'phone')->ignore($userModel->id)],
            'phone' => ['sometimes', 'required', 'string', Rule::unique('users', 'phone')->ignore($userModel->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|in:company_admin,cashier,station_manager,driver',
            'station_id' => 'nullable|exists:stations,id',
        ], [
            'email.unique' => 'Cet e-mail est déjà utilisé par un autre utilisateur.',
            'number.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'role.in' => 'Le rôle sélectionné n\'est pas valide.',
        ]);

        return DB::transaction(function () use ($validated, $userModel, $admin) {
            if (isset($validated['nom']) && isset($validated['prenoms'])) {
                $fullName = trim($validated['nom'] . ' ' . $validated['prenoms']);
            } else {
                $fullName = $validated['name'] ?? $userModel->name;
            }

            $phone = $validated['number'] ?? $validated['phone'] ?? $userModel->phone;

            $updateData = [
                'name' => $fullName,
                'email' => $validated['email'],
                'phone' => $phone,
                'role' => $validated['role'],
            ];

            // Seul l'admin de compagnie peut changer la gare d'affectation, ou le chef de gare garde sa gare
            if (!$admin->hasRole('station_manager') && isset($validated['station_id'])) {
                $updateData['station_id'] = $validated['station_id'];
            }

            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $userModel->update($updateData);
            $userModel->syncRoles([$validated['role']]);

            return response()->json([
                'message' => 'Membre du personnel mis à jour avec succès.',
                'user' => $userModel->fresh()->load(['roles', 'station'])
            ], 200);
        });
    }

    // Supprimer un membre du personnel
    public function destroy(Request $request, User $userModel)
    {
        $admin = $request->user();

        if ($userModel->company_id !== $admin->company_id) {
            return response()->json([
                'message' => 'Action non autorisée sur cet utilisateur.'
            ], 403);
        }

        if ($admin->hasRole('station_manager') && $userModel->station_id !== $admin->station_id) {
            return response()->json([
                'message' => 'Action non autorisée.'
            ], 403);
        }

        if ($userModel->id === $admin->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.'
            ], 422);
        }

        return DB::transaction(function () use ($userModel) {
            $userModel->syncRoles([]);
            $userModel->delete();

            return response()->json([
                'message' => 'Membre du personnel supprimé avec succès.'
            ], 200);
        });
    }
}
