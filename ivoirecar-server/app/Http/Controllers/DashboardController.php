<?php

namespace App\Http\Controllers;

use App\Models\Parcel;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Obtenir les statistiques globales d'administration.
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Base Query pour appliquer les filtres temporels sur les paiements
        $paymentQuery = Payment::where('status', 'completed');
        if ($startDate) $paymentQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate) $paymentQuery->whereDate('created_at', '<=', $endDate);

        // 1. Chiffre d'affaires global et ventilé
        $totalRevenue = (clone $paymentQuery)->sum('amount');
        $ticketsRevenue = (clone $paymentQuery)
            ->where('paymentable_type', 'App\Models\Ticket')
            ->sum('amount');
        $parcelsRevenue = (clone $paymentQuery)
            ->where('paymentable_type', 'App\Models\Parcel')
            ->sum('amount');

        // 2. Statistiques des Billets
        $ticketQuery = Ticket::query();
        if ($startDate) $ticketQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate) $ticketQuery->whereDate('created_at', '<=', $endDate);

        $ticketsStats = [
            'total' => (clone $ticketQuery)->count(),
            'paid' => (clone $ticketQuery)->whereIn('status', ['paid', 'boarded'])->count(),
            'reserved' => (clone $ticketQuery)->where('status', 'reserved')->count(),
            'cancelled' => (clone $ticketQuery)->where('status', 'cancelled')->count(),
        ];

        // 3. Statistiques des Colis
        $parcelQuery = Parcel::query();
        if ($startDate) $parcelQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate) $parcelQuery->whereDate('created_at', '<=', $endDate);

        $parcelsStats = [
            'total' => (clone $parcelQuery)->count(),
            'delivered' => (clone $parcelQuery)->where('status', 'delivered')->count(),
            'in_transit' => (clone $parcelQuery)->where('status', 'in_transit')->count(),
            'pending' => (clone $parcelQuery)->where('status', 'pending')->count(),
        ];

        // 4. Calcul du Taux d'Occupation des Véhicules (Sur les trajets effectués ou prévus)
        $tripQuery = Trip::query();
        if ($startDate) $tripQuery->whereDate('departure_time', '>=', $startDate);
        if ($endDate) $tripQuery->whereDate('departure_time', '<=', $endDate);

        $tripsData = (clone $tripQuery)->get(['total_seats', 'available_seats']);

        $totalCapacity = $tripsData->sum('total_seats');
        $totalBookedSeats = $tripsData->sum(fn($trip) => $trip->total_seats - $trip->available_seats);

        $occupancyRate = $totalCapacity > 0
            ? round(($totalBookedSeats / $totalCapacity) * 100, 2)
            : 0;

        // 5. Flotte de Véhicules
        $vehiclesStats = [
            'total' => Vehicle::count(),
            'available' => Vehicle::where('status', 'available')->count(),
            'in_transit' => Vehicle::where('status', 'in_transit')->count(),
            'maintenance' => Vehicle::where('status', 'maintenance')->count(),
        ];

        // 6. Répartition du Chiffre d'Affaires par Méthode de Paiement
        $paymentMethodsBreakdown = (clone $paymentQuery)
            ->select('payment_method', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_transactions'))
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'revenue' => [
                'total' => $totalRevenue,
                'tickets' => $ticketsRevenue,
                'parcels' => $parcelsRevenue,
            ],
            'occupancy_rate' => [
                'percentage' => $occupancyRate,
                'total_capacity' => $totalCapacity,
                'booked_seats' => $totalBookedSeats,
            ],
            'tickets' => $ticketsStats,
            'parcels' => $parcelsStats,
            'fleet' => $vehiclesStats,
            'payments_breakdown' => $paymentMethodsBreakdown,
        ]);
    }

    /**
     * Graphique des revenus quotidiens / mensuels.
     */
    public function revenueChart(Request $request): JsonResponse
    {
        $days = $request->query('days', 30);

        $chartData = Payment::where('status', 'completed')
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as transactions_count')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json($chartData);
    }
}
