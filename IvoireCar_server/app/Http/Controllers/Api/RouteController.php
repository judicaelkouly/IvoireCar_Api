<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    // Obtenir toutes les lignes de la compagnie (filtrées par gare si chef de gare)
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $request->query('company_id') ?? $user?->company_id;

        $query = Route::query()->with(['departureStation', 'arrivalStation', 'stops.station']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        // Si l'utilisateur connecté est un chef de gare, il ne voit que les lignes qui partent de sa gare
        if ($user && $user->hasRole('station_manager')) {
            $query->where('departure_station_id', $user->station_id);
        } 
        // L'admin de compagnie peut optionnellement filtrer par gare de départ via ?station_id=X
        elseif ($request->has('station_id')) {
            $query->where('departure_station_id', $request->station_id);
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

        $routes = Route::where('company_id', $companyId)
            ->with(['departureStation', 'arrivalStation', 'stops' => function ($q) {
                $q->orderBy('stop_order', 'asc');
            }])
            ->get();

        foreach ($routes as $route) {
            if ($route->departure_station_id === $depStation) {
                $stop = $route->stops->firstWhere('station_id', $destStation);
                if ($stop) {
                    return response()->json([
                        'route_id' => $route->id,
                        'price'    => (float) $stop->price,
                        'stop'     => $stop,
                        'route'    => $route,
                    ]);
                }

                if ($route->arrival_station_id === $destStation) {
                    return response()->json([
                        'route_id' => $route->id,
                        'price'    => (float) ($route->price ?? 0),
                        'route'    => $route,
                    ]);
                }
            }

            $depStop  = $route->stops->firstWhere('station_id', $depStation);
            $destStop = $route->stops->firstWhere('station_id', $destStation);

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
        $user = $request->user();
        if ($user && $route->company_id !== $user->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if ($user && $user->hasRole('station_manager') && $route->departure_station_id !== $user->station_id) {
            return response()->json(['message' => 'Action non autorisée sur cette ligne d\'une autre gare.'], 403);
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

        // Si c'est un chef de gare, on s'assure qu'il crée une ligne dont la gare de départ est SA gare
        if ($admin->hasRole('station_manager') && (int)$validated['departure_station_id'] !== $admin->station_id) {
            return response()->json([
                'message' => 'Vous ne pouvez créer des lignes qu\'à partir de votre propre gare.'
            ], 403);
        }

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
        $user = $request->user();

        if ($route->company_id !== $user->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if ($user->hasRole('station_manager') && $route->departure_station_id !== $user->station_id) {
            return response()->json(['message' => 'Action non autorisée sur cette ligne.'], 403);
        }

        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id'   => 'required|exists:stations,id|different:departure_station_id',
            'stops'                => 'nullable|array',
            'stops.*.station_id'   => 'required_with:stops|exists:stations,id',
            'stops.*.price'        => 'required_with:stops|numeric|min:0',
        ]);

        if ($user->hasRole('station_manager') && (int)$validated['departure_station_id'] !== $user->station_id) {
            return response()->json([
                'message' => 'La gare de départ de la ligne doit rester votre gare.'
            ], 403);
        }

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
        $user = $request->user();

        if ($route->company_id !== $user->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if ($user->hasRole('station_manager') && $route->departure_station_id !== $user->station_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $route->delete();

        return response()->json(['message' => 'Ligne supprimée avec succès.']);
    }
}
