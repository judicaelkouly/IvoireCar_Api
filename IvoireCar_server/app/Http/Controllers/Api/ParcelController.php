<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ParcelController extends Controller
{
    /**
     * 1. Déclarer et payer un envoi de colis (Status: pending)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id'           => 'required|exists:companies,id',
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id'   => 'required|exists:stations,id',
            'sender_name'          => 'nullable|string|max:255',
            'sender_phone'         => 'nullable|string|max:50',
            'estimated_value'      => 'required|numeric|min:0',
            'shipping_cost'        => 'nullable|numeric|min:0',
            'description'          => 'required|string',
            'receiver_name'        => 'nullable|string|max:255',
            'recipient_name'       => 'nullable|string|max:255',
            'receiver_phone'       => 'nullable|string|max:50',
            'recipient_phone'      => 'nullable|string|max:50',
            'payment_method'       => 'nullable|string|max:50',
            'weight_kg'            => 'nullable|numeric|min:0.1',
        ]);

        $trackingCode = 'PCL-' . strtoupper(Str::random(6));
        $shippingCost = $validated['shipping_cost'] ?? round($validated['estimated_value'] * 0.10);

        $recipientName = $validated['recipient_name'] ?? $validated['receiver_name'] ?? '';
        $recipientPhone = $validated['recipient_phone'] ?? $validated['receiver_phone'] ?? '';

        $parcel = Parcel::create([
            'tracking_code'        => $trackingCode,
            'company_id'           => $validated['company_id'],
            'sender_id'            => $request->user()?->id,
            'departure_station_id' => $validated['departure_station_id'],
            'arrival_station_id'   => $validated['arrival_station_id'],
            'sender_name'          => $validated['sender_name'] ?? $request->user()?->name,
            'sender_phone'         => $validated['sender_phone'] ?? $request->user()?->phone,
            'recipient_name'       => $recipientName,
            'recipient_phone'      => $recipientPhone,
            'description'          => $validated['description'],
            'estimated_value'      => $validated['estimated_value'],
            'shipping_cost'        => $shippingCost,
            'price'                => $shippingCost,
            'payment_method'       => $validated['payment_method'] ?? 'WAVE',
            'weight_kg'            => $validated['weight_kg'] ?? null,
            'status'               => 'pending',
        ]);

        return response()->json([
            'message' => 'Colis enregistré avec succès !',
            'parcel'  => $parcel,
        ], 201);
    }

    /**
     * 2. SUPER ADMIN : Liste de toutes les expéditions (avec infos destinataire et statut)
     */
    public function allParcels(Request $request)
    {
        $parcels = Parcel::with([
            'company:id,name,email,phone,logo',
            'departureStation:id,name,city',
            'arrivalStation:id,name,city',
            'sender:id,name,email,phone'
        ])
        ->latest()
        ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $parcels,
        ], 200);
    }

    /**
     * 3. COMPANY ADMIN : Liste des expéditions de sa compagnie (avec infos destinataire et statut)
     */
    public function companyParcels(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $request->get('company_id');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune compagnie associée à cet utilisateur.',
            ], 403);
        }

        $parcels = Parcel::with([
            'departureStation:id,name,city',
            'arrivalStation:id,name,city',
            'sender:id,name,email,phone'
        ])
        ->where('company_id', $companyId)
        ->latest()
        ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $parcels,
        ], 200);
    }

    /**
     * 4. Mise à jour de l'état de l'expédition
     */
    

public function updateStatus(Request $request, $id)
{
    // Stocker le résultat de la validation dans la variable $validated
    $validated = $request->validate([
        'status' => 'required|string|in:pending,received_at_station,in_transit,arrived_at_destination,collected,delivered,cancelled',
    ]);

    $parcel = Parcel::findOrFail($id);
    $parcel->status = $validated['status'];
    $parcel->save();

    $messages = [
        'pending'                => 'Expédition en attente.',
        'received_at_station'    => 'Colis réceptionné à la gare de départ.',
        'in_transit'             => 'Colis en cours d\'expédition.',
        'arrived_at_destination' => 'Colis arrivé à la gare de destination.',
        'collected'              => 'Colis remis au destinataire.',
        'delivered'              => 'Colis livré.',
        'cancelled'              => 'Expédition annulée.',
    ];

    return response()->json([
        'message' => $messages[$validated['status']] ?? 'Statut mis à jour avec succès.',
        'parcel'  => $parcel,
    ]);
}


    /**
     * 5. Remise du colis au récepteur avec contrôle de sécurité
     */
    public function collect(Request $request, $id)
    {
        $validated = $request->validate([
            'collector_name'      => 'required|string|max:255',
            'collector_phone'     => 'required|string|max:50',
            'collector_id_number' => 'required|string|max:100',
        ]);

        $parcel = Parcel::findOrFail($id);

        if ($parcel->status === 'collected') {
            return response()->json(['message' => 'Ce colis a déjà été récupéré.'], 400);
        }

        $parcel->update([
            'status'              => 'collected',
            'collector_name'      => $validated['collector_name'],
            'collector_phone'     => $validated['collector_phone'],
            'collector_id_number' => $validated['collector_id_number'],
            'collected_at'        => now(),
        ]);

        return response()->json([
            'message' => 'Colis remis au destinataire avec succès !',
            'parcel'  => $parcel,
        ]);
    }

    /**
     * 6. Suivi public de colis par code de tracking
     */
    public function track($trackingCode)
    {
        $parcel = Parcel::with(['company', 'departureStation', 'arrivalStation'])
            ->where('tracking_code', $trackingCode)
            ->first();

        if (!$parcel) {
            return response()->json(['message' => 'Colis introuvable.'], 404);
        }

        return response()->json($parcel);
    }

    /**
     * 7. Historique des colis expédiés par l'utilisateur connecté
     */
    public function myParcels(Request $request)
    {
        $parcels = Parcel::with(['company', 'departureStation', 'arrivalStation'])
            ->where('sender_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($parcels);
    }
}
