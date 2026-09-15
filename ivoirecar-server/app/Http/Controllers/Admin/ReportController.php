<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Liste globale de tous les tickets vendus sur la plateforme.
     */
    public function tickets(Request $request): JsonResponse
    {
        $query = Ticket::with([
            'user:id,name,phone',
            'trip.company:id,name',
            'trip.route:id,departure_city,arrival_city',
            'station:id,name'
        ]);

        // Filtre par compagnie
        if ($request->has('company_id')) {
            $query->whereHas('trip', function ($q) use ($request) {
                $q->where('company_id', $request->query('company_id'));
            });
        }

        // Filtre par statut du ticket (valid, cancelled, used)
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filtre par période
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('created_at', [$request->query('from_date'), $request->query('to_date')]);
        }

        $tickets = $query->latest()->paginate(20);

        return response()->json($tickets);
    }

    /**
     * Liste globale de tous les paiements effectués sur la plateforme.
     */
    public function payments(Request $request): JsonResponse
    {
        $query = Payment::with([
            'user:id,name,phone',
            'company:id,name'
        ]);

        // Filtre par compagnie
        if ($request->has('company_id')) {
            $query->where('company_id', $request->query('company_id'));
        }

        // Filtre par statut (completed, pending, failed)
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filtre par mode de paiement (wave, orange_money, mtn_momo, cash)
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }

        // Calcul des métriques globales de la requête actuelle
        $totalAmount = (clone $query)->where('status', 'completed')->sum('amount');

        $payments = $query->latest()->paginate(20);

        return response()->json([
            'total_revenue' => $totalAmount,
            'payments' => $payments,
        ]);
    }
    /**
 * Liste globale de tous les colis enregistrés sur la plateforme.
 */
public function parcels(Request $request): JsonResponse
{
    $query = Parcel::with([
        'company:id,name',
        'fromStation:id,name',
        'toStation:id,name'
    ]);

    // Filtre par compagnie
    if ($request->has('company_id')) {
        $query->where('company_id', $request->query('company_id'));
    }

    // Filtre par statut (pending, in_transit, delivered, cancelled)
    if ($request->has('status')) {
        $query->where('status', $request->query('status'));
    }

    // Recherche par numéro de suivi, expéditeur ou destinataire
    if ($request->has('search')) {
        $search = $request->query('search');
        $query->where(function ($q) use ($search) {
            $q->where('tracking_number', 'LIKE', "%{$search}%")
              ->orWhere('sender_phone', 'LIKE', "%{$search}%")
              ->orWhere('recipient_phone', 'LIKE', "%{$search}%");
        });
    }

    $parcels = $query->latest()->paginate(20);

    return response()->json($parcels);
}

}
