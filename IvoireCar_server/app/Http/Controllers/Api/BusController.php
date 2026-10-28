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
     * Liste de tous les bus (Filtré par gare si chef de gare, ou global pour l'admin)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Bus::where('company_id', $user->company_id)
            ->with(['driver:id,name,email,phone', 'station:id,name,city']);

        // Si l'utilisateur est un chef de gare, il ne voit que les bus de sa gare
        if ($user->hasRole('station_manager')) {
            $query->where('station_id', $user->station_id);
        }
        // Si l'admin souhaite filtrer par gare spécifique
        elseif ($request->has('station_id')) {
            $query->where('station_id', $request->station_id);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Afficher un bus spécifique pour l'édition (Méthode SHOW)
     */
    public function show(Request $request, $id)
    {
        $admin = $request->user();

        $bus = Bus::where('company_id', $admin->company_id)
            ->with(['driver:id,name,email,phone', 'station:id,name,city'])
            ->find($id);

        if (!$bus) {
            return response()->json([
                'message' => 'Véhicule introuvable ou vous n\'avez pas la permission d\'y accéder.'
            ], 404);
        }

        // Vérification si chef de gare
        if ($admin->hasRole('station_manager') && $bus->station_id !== $admin->station_id) {
            return response()->json([
                'message' => 'Action non autorisée sur ce véhicule d\'une autre gare.'
            ], 403);
        }

        return response()->json($bus);
    }

    /**
     * Récupérer les chauffeurs de la compagnie (optionnellement filtrés par gare) pour le select React
     */
    public function getDrivers(Request $request)
    {
        $user = $request->user();

        $query = User::where('company_id', $user->company_id)
            ->where('role', 'driver');

        // Si c'est un chef de gare, il ne liste que les chauffeurs affectés à sa gare
        if ($user->hasRole('station_manager')) {
            $query->where('station_id', $user->station_id);
        }

        $drivers = $query->select('id', 'name', 'email', 'phone', 'station_id')->get();

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
            'station_id' => 'nullable|exists:stations,id',
            'status' => 'nullable|string|in:active,maintenance,inactive',
        ], [
            'registration_number.required' => 'L\'immatriculation est obligatoire.',
            'model.required' => 'Le modèle est obligatoire.',
            'capacity.required' => 'La capacité est obligatoire.',
            'driver_id.exists' => 'Le chauffeur sélectionné est invalide.',
            'station_id.exists' => 'La gare sélectionnée est invalide.',
            'status.in' => 'Le statut sélectionné est invalide.',
        ]);

        // Détermination de la gare : si chef de gare, on force sa gare. Sinon on prend celle envoyée ou null
        $stationId = $admin->hasRole('station_manager') ? $admin->station_id : ($validated['station_id'] ?? null);

        if (!$stationId && !$admin->hasRole('company_admin')) {
            return response()->json([
                'message' => 'Le véhicule doit impérativement être affecté à une gare.'
            ], 422);
        }

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
            'station_id' => $stationId,
            'company_id' => $admin->company_id,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Véhicule créé et affecté avec succès !',
            'bus' => $bus->load(['driver:id,name,email,phone', 'station:id,name,city']),
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

        // Vérification chef de gare
        if ($admin->hasRole('station_manager') && $bus->station_id !== $admin->station_id) {
            return response()->json([
                'message' => 'Action non autorisée sur un véhicule d\'une autre gare.'
            ], 403);
        }

        $validated = $request->validate([
            'registration_number' => 'required|string|max:50',
            'model' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1|max:200',
            'driver_id' => 'nullable|exists:users,id',
            'station_id' => 'nullable|exists:stations,id',
            'status' => 'required|string|in:active,maintenance,inactive',
        ], [
            'registration_number.required' => 'L\'immatriculation est obligatoire.',
            'model.required' => 'Le modèle est obligatoire.',
            'capacity.required' => 'La capacité est obligatoire.',
            'driver_id.exists' => 'Le chauffeur sélectionné est invalide.',
            'station_id.exists' => 'La gare sélectionnée est invalide.',
            'status.required' => 'Le statut du véhicule est obligatoire.',
            'status.in' => 'Le statut sélectionné est invalide.',
        ]);

        $exists = Bus::where('company_id', $admin->company_id)
            ->where('registration_number', strtoupper($validated['registration_number']))
            ->where('id', '!=', $bus->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Un autre véhicule possède déjà cette immatriculation dans votre compagnie.'
            ], 422);
        }

        // Le chef de gare ne peut pas changer la gare du bus, seul l'admin le peut
        $stationId = $admin->hasRole('station_manager') ? $bus->station_id : ($validated['station_id'] ?? $bus->station_id);

        $bus->update([
            'registration_number' => strtoupper($validated['registration_number']),
            'model' => $validated['model'],
            'capacity' => $validated['capacity'],
            'user_id' => $validated['driver_id'] ?? null,
            'station_id' => $stationId,
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Véhicule mis à jour avec succès !',
            'bus' => $bus->fresh()->load(['driver:id,name,email,phone', 'station:id,name,city']),
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

        if ($admin->hasRole('station_manager') && $bus->station_id !== $admin->station_id) {
            return response()->json([
                'message' => 'Action non autorisée.'
            ], 403);
        }

        $bus->delete();

        return response()->json([
            'message' => 'Véhicule supprimé avec succès.'
        ], 200);
    }
}
