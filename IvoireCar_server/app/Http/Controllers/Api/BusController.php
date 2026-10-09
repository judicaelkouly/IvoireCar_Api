<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusController extends Controller
{
    /**
     * Liste de tous les bus de la compagnie
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $buses = Bus::where('company_id', $companyId)
            ->with('driver:id,name,email,phone')
            ->latest()
            ->get();

        return response()->json($buses);
    }

    /**
     * Afficher un bus spécifique pour l'édition (Méthode SHOW)
     */
    public function show(Request $request, $id)
    {
        $admin = $request->user();

        $bus = Bus::where('company_id', $admin->company_id)
            ->with('driver:id,name,email,phone')
            ->find($id);

        if (!$bus) {
            return response()->json([
                'message' => 'Véhicule introuvable ou vous n\'avez pas la permission d\'y accéder.'
            ], 404);
        }

        return response()->json($bus);
    }

    /**
     * Récupérer les chauffeurs (role: driver) de la compagnie pour le select React
     */
    public function getDrivers(Request $request)
    {
        $companyId = $request->user()->company_id;

        $drivers = User::where('company_id', $companyId)
            ->where('role', 'driver')
            ->select('id', 'name', 'email', 'phone')
            ->get();

        return response()->json($drivers);
    }

    /**
     * Enregistrer un nouveau bus
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        if (!$admin->company_id) {
            return response()->json([
                'message' => 'Vous devez appartenir à une compagnie pour ajouter un véhicule.'
            ], 403);
        }

        $validated = $request->validate([
            'registration_number' => 'required|string|max:50',
            'model' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1|max:200',
            'driver_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|in:active,maintenance,inactive',
        ], [
            'registration_number.required' => 'L\'immatriculation est obligatoire.',
            'model.required' => 'Le modèle est obligatoire.',
            'capacity.required' => 'La capacité est obligatoire.',
            'driver_id.exists' => 'Le chauffeur sélectionné est invalide.',
            'status.in' => 'Le statut sélectionné est invalide.',
        ]);

        // Vérification de l'unicité de l'immatriculation au sein de la compagnie
        $exists = Bus::where('company_id', $admin->company_id)
            ->where('registration_number', strtoupper($validated['registration_number']))
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Un véhicule avec cette immatriculation existe déjà dans votre compagnie.'
            ], 422);
        }

        $bus = Bus::create([
            'registration_number' => strtoupper($validated['registration_number']),
            'model' => $validated['model'],
            'capacity' => $validated['capacity'],
            'user_id' => $validated['driver_id'] ?? null,
            'company_id' => $admin->company_id,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Véhicule créé avec succès !',
            'bus' => $bus->load('driver:id,name,email,phone'),
        ], 201);
    }

    /**
     * Mettre à jour un bus
     */
    public function update(Request $request, $id)
    {
        $admin = $request->user();

        $bus = Bus::where('company_id', $admin->company_id)->find($id);

        if (!$bus) {
            return response()->json([
                'message' => 'Action non autorisée ou véhicule introuvable.'
            ], 403);
        }

        $validated = $request->validate([
            'registration_number' => 'required|string|max:50',
            'model' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1|max:200',
            'driver_id' => 'nullable|exists:users,id',
            'status' => 'required|string|in:active,maintenance,inactive',
        ], [
            'registration_number.required' => 'L\'immatriculation est obligatoire.',
            'model.required' => 'Le modèle est obligatoire.',
            'capacity.required' => 'La capacité est obligatoire.',
            'driver_id.exists' => 'Le chauffeur sélectionné est invalide.',
            'status.required' => 'Le statut du véhicule est obligatoire.',
            'status.in' => 'Le statut sélectionné est invalide.',
        ]);

        // Vérifier l'unicité de l'immatriculation en excluant le bus actuel
        $exists = Bus::where('company_id', $admin->company_id)
            ->where('registration_number', strtoupper($validated['registration_number']))
            ->where('id', '!=', $bus->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Un autre véhicule possède déjà cette immatriculation dans votre compagnie.'
            ], 422);
        }

        $bus->update([
            'registration_number' => strtoupper($validated['registration_number']),
            'model' => $validated['model'],
            'capacity' => $validated['capacity'],
            'user_id' => $validated['driver_id'] ?? null,
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Véhicule mis à jour avec succès !',
            'bus' => $bus->fresh()->load('driver:id,name,email,phone'),
        ], 200);
    }

    /**
     * Supprimer un bus
     */
    public function destroy(Request $request, $id)
    {
        $admin = $request->user();

        $bus = Bus::where('company_id', $admin->company_id)->find($id);

        if (!$bus) {
            return response()->json([
                'message' => 'Action non autorisée ou véhicule introuvable.'
            ], 403);
        }

        $bus->delete();

        return response()->json([
            'message' => 'Véhicule supprimé avec succès.'
        ], 200);
    }
}
