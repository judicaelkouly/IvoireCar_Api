<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\Route as TransportRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Trip::with(['route.departureStation', 'route.arrivalStation', 'vehicle', 'driver']);

        // Filtrer par gare de départ
        if ($request->has('departure_station_id')) {
            $query->whereHas('route', function ($q) use ($request) {
                $q->where('departure_station_id', $request->query('departure_station_id'));
            });
        }

        // Filtrer par gare d'arrivée
        if ($request->has('arrival_station_id')) {
            $query->whereHas('route', function ($q) use ($request) {
                $q->where('arrival_station_id', $request->query('arrival_station_id'));
            });
        }

        // Filtrer par date de départ
        if ($request->has('date')) {
            $query->whereDate('departure_time', $request->query('date'));
        }

        // Filtrer par statut
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        } else {
            // Par défaut : trajets prévus uniquement
            $query->where('status', 'scheduled');
        }

        return response()->json($query->orderBy('departure_time', 'asc')->paginate(15));
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Récupérer le véhicule pour obtenir la capacité de sièges
        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);

        // Si le prix n'est pas précisé, utiliser le prix par défaut de la ligne
        if (!isset($validated['price'])) {
            $route = TransportRoute::findOrFail($validated['route_id']);
            $validated['price'] = $route->price;
        }

        // Affecter le nombre de places disponibles en fonction de la capacité du véhicule
        $validated['total_seats'] = $vehicle->total_seats;
        $validated['available_seats'] = $vehicle->total_seats;

        $trip = Trip::create($validated);

        return response()->json([
            'message' => 'Trajet programmé avec succès.',
            'trip' => $trip->load(['route.departureStation', 'route.arrivalStation', 'vehicle']),
        ], 201);
    }

    public function show(Trip $trip): JsonResponse
    {
        return response()->json($trip->load([
            'route.departureStation',
            'route.arrivalStation',
            'route.stops.station',
            'vehicle',
            'driver',
            'tickets'
        ]));
    }

    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        $validated = $request->validated();

        // Recalcul des places si le véhicule est modifié
        if (isset($validated['vehicle_id']) && $validated['vehicle_id'] !== $trip->vehicle_id) {
            $newVehicle = Vehicle::findOrFail($validated['vehicle_id']);
            $seatsDifference = $newVehicle->total_seats - $trip->total_seats;

            $validated['total_seats'] = $newVehicle->total_seats;
            $validated['available_seats'] = max(0, $trip->available_seats + $seatsDifference);
        }

        $trip->update($validated);

        return response()->json([
            'message' => 'Trajet mis à jour avec succès.',
            'trip' => $trip->load(['route.departureStation', 'route.arrivalStation', 'vehicle']),
        ]);
    }

    public function destroy(Trip $trip): JsonResponse
    {
        // Empêcher la suppression si des billets ont déjà été vendus
        if ($trip->tickets()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer un trajet ayant déjà des billets réservés. Annulez plutôt le trajet.'
            ], 422);
        }

        $trip->delete();

        return response()->json([
            'message' => 'Trajet supprimé avec succès.',
        ]);
    }
}
