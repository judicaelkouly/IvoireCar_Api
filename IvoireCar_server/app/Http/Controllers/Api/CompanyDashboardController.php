<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Parcel;
use App\Models\Departure;
use App\Models\Bus;
use App\Models\User;
use Illuminate\Http\Request;

class CompanyDashboardController extends Controller
{
    /**
     * KPIs et Statistiques de la Compagnie
     */
    public function stats(Request $request)
    {
        $companyId = $request->user()->company_id;

        // Total des revenus générés par les réservations de billets
        $totalRevenueTickets = Reservation::where('company_id', $companyId)
            ->whereIn('payment_status', ['PAID', 'paid', 'COMPLETED'])
            ->sum('amount');

        // Total des revenus générés par les expéditions de colis
        $totalRevenueParcels = Parcel::where('company_id', $companyId)
            ->whereNotIn('status', ['cancelled'])
            ->sum('shipping_cost');

        // Nombre de tickets / réservations vendus
        $totalTicketsSold = Reservation::where('company_id', $companyId)
            ->whereIn('payment_status', ['PAID', 'paid', 'COMPLETED'])
            ->count();

        // Nombre d'utilisateurs / membre du personnel rattachés à la compagnie
        $totalStaff = User::where('company_id', $companyId)->count();

        return response()->json([
            'total_buses'           => Bus::where('company_id', $companyId)->count(),
            'total_trips'           => Departure::where('company_id', $companyId)->count(),
            'total_tickets_sold'    => $totalTicketsSold,
            'total_parcels_handled' => Parcel::where('company_id', $companyId)->count(),
            'total_staff'           => $totalStaff,
            'ticket_revenue_fcfa'   => (float) $totalRevenueTickets,
            'parcel_revenue_fcfa'   => (float) $totalRevenueParcels,
            'company_revenue_fcfa'  => (float) ($totalRevenueTickets + $totalRevenueParcels),
        ]);
    }

    public function recentOperations(Request $request)
    {
        $companyId = $request->user()->company_id;

        // 1. Récupérer les 10 dernières réservations de la compagnie
        $recentTickets = Reservation::with('user')
            ->where('company_id', $companyId)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($ticket) {
                return [
                    'id'            => $ticket->id,
                    'type'          => 'ticket',
                    'reference'     => $ticket->reference_number ?? $ticket->ticket_number ?? "Billet #{$ticket->id}",
                    'user_name'     => $ticket->user ? $ticket->user->name : ($ticket->passenger_name ?? 'Client Inconnu'),
                    'price'         => (float) ($ticket->amount ?? $ticket->price ?? 0),
                    'created_at'    => $ticket->created_at,
                ];
            });

        // 2. Récupérer les 10 dernières expéditions de colis de la compagnie
        $recentParcels = Parcel::where('company_id', $companyId)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($parcel) {
                return [
                    'id'            => $parcel->id,
                    'type'          => 'colis',
                    'tracking_code' => $parcel->tracking_code ?? $parcel->code ?? "Colis #{$parcel->id}",
                    'sender_name'   => $parcel->sender_name ?? 'Expéditeur',
                    'shipping_cost' => (float) ($parcel->shipping_cost ?? $parcel->price ?? 0),
                    'created_at'    => $parcel->created_at,
                ];
            });

        // 3. Fusionner, trier par date et limiter à 10 éléments
        $operations = $recentTickets->concat($recentParcels)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

        return response()->json($operations);
    }
}
