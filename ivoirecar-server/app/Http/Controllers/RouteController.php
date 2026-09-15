<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRouteRequest;
use App\Http\Requests\UpdateRouteRequest;
use App\Models\Route;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Route::with(['company', 'departureStation', 'arrivalStation', 'stops.station']);

        if ($request->has('company_id')) {
            $query->where('company_id', $request->query('company_id'));
        }

        if ($request->has('departure_station_id')) {
            $query->where('departure_station_id', $request->query('departure_station_id'));
        }

        if ($request->has('arrival_station_id')) {
            $query->where('arrival_station_id', $request->query('arrival_station_id'));
        }

        return response()->json($query->paginate(15));
    }

    public function store(StoreRouteRequest $request): JsonResponse
    {
        $route = Route::create($request->validated());

        return response()->json([
            'message' => 'Ligne de transport créée avec succès.',
            'route' => $route->load(['company', 'departureStation', 'arrivalStation']),
        ], 201);
    }

    public function show(Route $route): JsonResponse
    {
        return response()->json($route->load([
            'company',
            'departureStation',
            'arrivalStation',
            'stops' => function ($query) {
                $query->orderBy('stop_order', 'asc')->with('station');
            }
        ]));
    }

    public function update(UpdateRouteRequest $request, Route $route): JsonResponse
    {
        $route->update($request->validated());

        return response()->json([
            'message' => 'Ligne de transport mise à jour avec succès.',
            'route' => $route->load(['company', 'departureStation', 'arrivalStation']),
        ]);
    }

    public function destroy(Route $route): JsonResponse
    {
        $route->delete();

        return response()->json([
            'message' => 'Ligne de transport supprimée avec succès.',
        ]);
    }
}
