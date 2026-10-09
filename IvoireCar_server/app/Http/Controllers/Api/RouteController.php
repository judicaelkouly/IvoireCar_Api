<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    // Obtenir toutes les lignes de la compagnie avec leurs escales
    public function index(Request $request)
    {
        $companyId = $request->query('company_id') ?? $request->user()?->company_id;

        $query = Route::query()->with(['departureStation', 'arrivalStation', 'stops.station']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Rechercher la ligne correspondante et calculer le tarif selon les gares sélectionnées
     * URL: GET /api/company/routes/search?company_id=X&departure_station_id=Y&destination_station_id=Z
     */
     
    public function search(Request $request)
    {
        $validated = $request->validate([
            'company_id'             => 'required|exists:companies,id',
            'departure_station_id'   => 'required|exists:stations,id',
            'destination_station_id' => 'required|exists:stations,id|different:departure_station_id',
        ]);

        $companyId   = $validated['company_id'];
        $depStation  = (int) $validated['departure_station_id'];
        $destStation = (int) $validated['destination_station_id'];

        // Charge toutes les lignes de la compagnie avec leurs escales ordonnées
        $routes = Route::where('company_id', $companyId)
            ->with(['departureStation', 'arrivalStation', 'stops' => function ($q) {
                $q->orderBy('stop_order', 'asc');
            }])
            ->get();

        foreach ($routes as $route) {

            // CAS 1 : La gare de départ est le départ principal de la ligne
            if ($route->departure_station_id === $depStation) {

                // 1. On cherche prioritairement le tarif dans les escales (y compris si c'est la dernière escale/terminus)
                $stop = $route->stops->firstWhere('station_id', $destStation);
                if ($stop) {
                    return response()->json([
                        'route_id' => $route->id,
                        'price'    => (float) $stop->price,
                        'stop'     => $stop,
                        'route'    => $route,
                    ]);
                }

                // 2. Si la destination est le terminus mais n'est pas saisie dans stops, fallback sur $route->price
                if ($route->arrival_station_id === $destStation) {
                    return response()->json([
                        'route_id' => $route->id,
                        'price'    => (float) ($route->price ?? 0),
                        'route'    => $route,
                    ]);
                }
            }

            // CAS 2 : Le départ ET la destination sont deux escales intermédiaires sur cette ligne
            $depStop  = $route->stops->firstWhere('station_id', $depStation);
            $destStop = $route->stops->firstWhere('station_id', $destStation);

            // On vérifie que la gare de départ est située AVANT la destination dans le parcours
            if ($depStop && $destStop && ($depStop->stop_order < $destStop->stop_order)) {
                return response()->json([
                    'route_id'         => $route->id,
                    'price'            => (float) $destStop->price,
                    'departure_stop'   => $depStop,
                    'destination_stop' => $destStop,
                    'route'            => $route,
                ]);
            }
        }

        return response()->json([
            'message' => 'Aucune ligne ni escale disponible entre ces deux gares.'
        ], 444);
    }

    // Afficher une ligne spécifique
    public function show(Request $request, Route $route)
    {
        if ($request->user() && $route->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        return response()->json(
            $route->load(['departureStation', 'arrivalStation', 'stops.station'])
        );
    }

    // Créer une Ligne avec ses Escales et Prix
    public function store(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id'   => 'required|exists:stations,id|different:departure_station_id',
            'stops'                => 'required|array|min:1',
            'stops.*.station_id'   => 'required|exists:stations,id',
            'stops.*.price'        => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $admin) {
            $route = Route::create([
                'company_id'           => $admin->company_id,
                'departure_station_id' => $validated['departure_station_id'],
                'arrival_station_id'   => $validated['arrival_station_id'],
                'name'                 => $validated['name'],
            ]);

            foreach ($validated['stops'] as $index => $stop) {
                $route->stops()->create([
                    'station_id' => $stop['station_id'],
                    'stop_order' => $index + 1,
                    'price'      => $stop['price'],
                ]);
            }

            return response()->json([
                'message' => 'Ligne de transport créée avec succès !',
                'route'   => $route->load(['departureStation', 'arrivalStation', 'stops.station']),
            ], 201);
        });
    }

    // Mettre à jour une ligne et ses escales
    public function update(Request $request, Route $route)
    {
        if ($route->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id'   => 'required|exists:stations,id|different:departure_station_id',
            'stops'                => 'nullable|array',
            'stops.*.station_id'   => 'required_with:stops|exists:stations,id',
            'stops.*.price'        => 'required_with:stops|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $route) {
            $route->update([
                'name'                 => $validated['name'],
                'departure_station_id' => $validated['departure_station_id'],
                'arrival_station_id'   => $validated['arrival_station_id'],
            ]);

            if (array_key_exists('stops', $validated)) {
                $route->stops()->delete();

                foreach ($validated['stops'] as $index => $stop) {
                    $route->stops()->create([
                        'station_id' => $stop['station_id'],
                        'stop_order' => $index + 1,
                        'price'      => $stop['price'],
                    ]);
                }
            }

            return response()->json([
                'message' => 'Ligne de transport mise à jour avec succès !',
                'route'   => $route->fresh()->load(['departureStation', 'arrivalStation', 'stops.station']),
            ]);
        });
    }

    // Supprimer une ligne
    public function destroy(Request $request, Route $route)
    {
        if ($route->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $route->delete();

        return response()->json(['message' => 'Ligne supprimée avec succès.']);
    }
}
