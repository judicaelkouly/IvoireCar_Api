<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParcelRequest;
use App\Models\Parcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ParcelController extends Controller
{
    /**
     * Liste des colis (avec filtrage par compagnie et recherche)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Parcel::with(['departureStation', 'destinationStation', 'trip', 'sender']);

        // Isolation multi-tenant : si l'utilisateur appartient à une compagnie, il ne voit que ses colis
        if ($user && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        // Filtrer par gare de départ
        if ($request->has('departure_station_id')) {
            $query->where('departure_station_id', $request->query('departure_station_id'));
        }

        // Filtrer par gare de destination
        if ($request->has('destination_station_id')) {
            $query->where('destination_station_id', $request->query('destination_station_id'));
        }

        // Filtrer par statut
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Recherche par numéro de suivi ou nom/téléphone (expéditeur ou destinataire)
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhere('sender_name', 'like', "%{$search}%")
                  ->orWhere('sender_phone', 'like', "%{$search}%")
                  ->orWhere('receiver_name', 'like', "%{$search}%")
                  ->orWhere('receiver_phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * Enregistrement d'un nouveau colis
     */
    public function store(StoreParcelRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Génération d'un numéro de suivi unique (ex: PCL-8X92K4A1)
        $trackingNumber = 'PCL-' . strtoupper(Str::random(8));

        $parcel = Parcel::create([
            ...$validated,
            'company_id'      => $user->company_id ?? $validated['company_id'] ?? null,
            'sender_id'       => $user->id ?? null,
            'tracking_number' => $trackingNumber,
            'payment_method'  => $validated['payment_method'] ?? 'cash',
            'payment_status'  => isset($validated['payment_method']) && $validated['payment_method'] !== 'cash' ? 'pending' : 'completed',
            'status'          => 'pending',
        ]);

        return response()->json([
            'message' => 'Colis enregistré avec succès.',
            'parcel'  => $parcel->load(['departureStation', 'destinationStation', 'trip']),
        ], 201);
    }

    /**
     * Détails d'un colis spécifique
     */
    public function show(Parcel $parcel): JsonResponse
    {
        return response()->json($parcel->load([
            'departureStation',
            'destinationStation',
            'trip.vehicle',
            'sender'
        ]));
    }

    /**
     * Suivi public d'un colis via son numéro de suivi (Sans Auth)
     */
    public function track(string $trackingNumber): JsonResponse
    {
        $parcel = Parcel::where('tracking_number', $trackingNumber)
            ->with(['departureStation', 'destinationStation', 'trip.vehicle'])
            ->firstOrFail();

        return response()->json([
            'tracking_number'     => $parcel->tracking_number,
            'status'              => $parcel->status,
            'sender_name'         => $parcel->sender_name,
            'receiver_name'       => $parcel->receiver_name,
            'departure_station'   => $parcel->departureStation->name ?? null,
            'destination_station' => $parcel->destinationStation->name ?? null,
            'delivered_at'        => $parcel->delivered_at,
            'created_at'          => $parcel->created_at,
            'updated_at'          => $parcel->updated_at,
        ]);
    }

    /**
     * Mettre à jour le statut d'un colis (Transit, Retrait / Livraison)
     */
    public function updateStatus(Request $request, Parcel $parcel): JsonResponse
    {
        $validated = $request->validate([
            'status'                  => 'required|string|in:pending,in_transit,delivered,cancelled',
            'receiver_identity_name'  => 'required_if:status,delivered|nullable|string|max:255',
            'receiver_identity_phone' => 'required_if:status,delivered|nullable|string|max:20',
            'receiver_id_card_number' => 'required_if:status,delivered|nullable|string|max:100',
        ]);

        $dataToUpdate = [
            'status' => $validated['status'],
        ];

        // Si l'agent valide le retrait en gare
        if ($validated['status'] === 'delivered') {
            $dataToUpdate['receiver_identity_name']  = $validated['receiver_identity_name'];
            $dataToUpdate['receiver_identity_phone'] = $validated['receiver_identity_phone'];
            $dataToUpdate['receiver_id_card_number'] = $validated['receiver_id_card_number'];
            $dataToUpdate['delivered_at']            = now();
        }

        $parcel->update($dataToUpdate);

        return response()->json([
            'message' => 'Statut du colis mis à jour avec succès.',
            'parcel'  => $parcel->fresh()->load(['departureStation', 'destinationStation', 'trip']),
        ]);
    }

    /**
     * Annulation / Suppression d'un colis
     */
    public function destroy(Parcel $parcel): JsonResponse
    {
        if (in_array($parcel->status, ['in_transit', 'delivered'])) {
            return response()->json([
                'message' => 'Impossible de supprimer un colis en transit ou déjà livré.'
            ], 422);
        }

        $parcel->delete();

        return response()->json([
            'message' => 'Colis supprimé avec succès.',
        ]);
    }
}
