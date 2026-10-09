<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Models\User;
use Illuminate\Http\Request;

class StationController extends Controller
{
    /**
     * Liste de toutes les gares de la compagnie
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $stations = Station::where('company_id', $companyId)
            ->with('manager:id,name,email,phone')
            ->latest()
            ->get();

        return response()->json($stations);
    }

    /**
     * Récupérer les chefs de gare de la compagnie pour le select React
     */
    public function getStationManagers(Request $request)
    {
        $companyId = $request->user()->company_id;

        $managers = User::where('company_id', $companyId)
            ->where('role', 'station_manager')
            ->select('id', 'name', 'email', 'phone')
            ->get();

        return response()->json($managers);
    }

    /**
     * Création d'une gare
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        if (!$admin->company_id) {
            return response()->json([
                'message' => 'Vous devez appartenir à une compagnie pour créer une gare.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'manager_id' => 'nullable|exists:users,id',
        ], [
            'name.required' => 'Le nom de la gare est obligatoire.',
            'city.required' => 'La ville est obligatoire.',
            'address.required' => 'L\'adresse est obligatoire.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'manager_id.exists' => 'Le chef de gare sélectionné est invalide.',
        ]);

        $station = Station::create([
            'name' => $validated['name'],
            'city' => $validated['city'],
            'address' => $validated['address'],
            'phone' => $validated['phone'],
            'user_id' => $validated['manager_id'] ?? null,
            'company_id' => $admin->company_id,
        ]);

        return response()->json([
            'message' => 'Gare créée avec succès !',
            'station' => $station->load('manager:id,name,email,phone'),
        ], 201);
    }

        public function show($id)
    {
        $station = Station::findOrFail($id);
        return response()->json(['data' => $station]);
    }
        /**
     * Mise à jour d'une gare
     */
    public function update(Request $request, Station $station)
    {
        $admin = $request->user();

        // Vérification de sécurité : la gare doit appartenir à la compagnie de l'admin
        if ($station->company_id !== $admin->company_id) {
            return response()->json([
                'message' => 'Action non autorisée sur cette gare.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'manager_id' => 'nullable|exists:users,id',
        ], [
            'name.required' => 'Le nom de la gare est obligatoire.',
            'city.required' => 'La ville est obligatoire.',
            'address.required' => 'L\'adresse est obligatoire.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'manager_id.exists' => 'Le chef de gare sélectionné est invalide.',
        ]);

        $station->update([
            'name' => $validated['name'],
            'city' => $validated['city'],
            'address' => $validated['address'],
            'phone' => $validated['phone'],
            'user_id' => $validated['manager_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Gare mise à jour avec succès !',
            'station' => $station->fresh()->load('manager:id,name,email,phone'),
        ], 200);
    }

    /**
     * Suppression d'une gare
     */
    public function destroy(Request $request, Station $station)
    {
        $admin = $request->user();

        if ($station->company_id !== $admin->company_id) {
            return response()->json([
                'message' => 'Action non autorisée sur cette gare.'
            ], 403);
        }

        $station->delete();

        return response()->json([
            'message' => 'Gare supprimée avec succès.'
        ], 200);
    }
}
