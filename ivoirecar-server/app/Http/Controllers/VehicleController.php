<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::with(['company', 'station']);

        // Filtrer par compagnie
        if ($request->has('company_id')) {
            $query->where('company_id', $request->query('company_id'));
        }

        // Filtrer par gare d'attache
        if ($request->has('station_id')) {
            $query->where('station_id', $request->query('station_id'));
        }

        // Filtrer par statut (available, in_transit, maintenance, etc.)
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(15));
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = Vehicle::create($request->validated());

        return response()->json([
            'message' => 'Véhicule ajouté avec succès.',
            'vehicle' => $vehicle->load(['company', 'station']),
        ], 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        return response()->json($vehicle->load(['company', 'station']));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->update($request->validated());

        return response()->json([
            'message' => 'Véhicule mis à jour avec succès.',
            'vehicle' => $vehicle->load(['company', 'station']),
        ]);
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $vehicle->delete();

        return response()->json([
            'message' => 'Véhicule supprimé avec succès.',
        ]);
    }
}
