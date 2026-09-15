<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRouteStopRequest;
use App\Http\Requests\UpdateRouteStopRequest;
use App\Models\RouteStop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteStopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RouteStop::with(['route', 'station']);

        if ($request->has('route_id')) {
            $query->where('route_id', $request->query('route_id'))->orderBy('stop_order', 'asc');
        }

        return response()->json($query->get());
    }

    public function store(StoreRouteStopRequest $request): JsonResponse
    {
        $routeStop = RouteStop::create($request->validated());

        return response()->json([
            'message' => 'Arrêt intermédiaire ajouté avec succès.',
            'stop' => $routeStop->load(['route', 'station']),
        ], 201);
    }

    public function show(RouteStop $routeStop): JsonResponse
    {
        return response()->json($routeStop->load(['route', 'station']));
    }

    public function update(UpdateRouteStopRequest $request, RouteStop $routeStop): JsonResponse
    {
        $routeStop->update($request->validated());

        return response()->json([
            'message' => 'Arrêt intermédiaire mis à jour avec succès.',
            'stop' => $routeStop->load(['route', 'station']),
        ]);
    }

    public function destroy(RouteStop $routeStop): JsonResponse
    {
        $routeStop->delete();

        return response()->json([
            'message' => 'Arrêt intermédiaire supprimé avec succès.',
        ]);
    }
}
