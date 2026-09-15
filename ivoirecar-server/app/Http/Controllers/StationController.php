<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStationRequest;
use App\Http\Requests\UpdateStationRequest;
use App\Models\Company;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Station::with('company');

        // Filtrer par compagnie si demandé
        if ($request->has('company_id')) {
            $query->where('company_id', $request->query('company_id'));
        }

        // Filtrer par ville si demandé
        if ($request->has('city')) {
            $query->where('city', 'ILIKE', '%' . $request->query('city') . '%');
        }

        return response()->json($query->paginate(15));
    }

    public function store(StoreStationRequest $request): JsonResponse
    {
        $station = Station::create($request->validated());

        return response()->json([
            'message' => 'Gare créée avec succès.',
            'station' => $station->load('company'),
        ], 201);
    }

    public function show(Station $station): JsonResponse
    {
        return response()->json($station->load('company'));
    }

    public function update(UpdateStationRequest $request, Station $station): JsonResponse
    {
        $station->update($request->validated());

        return response()->json([
            'message' => 'Gare mise à jour avec succès.',
            'station' => $station->load('company'),
        ]);
    }

    public function destroy(Station $station): JsonResponse
    {
        $station->delete();

        return response()->json([
            'message' => 'Gare supprimée avec succès.',
        ]);
    }
}
