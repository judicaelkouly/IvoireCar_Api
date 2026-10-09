<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Models\Parcel;
use App\Models\Reservation;
use App\Models\Bus;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    /**
     * 1. KPIs Globaux de la plateforme
     */
    public function stats()
    {
        // Chiffre d'affaires des réservations (Tickets avec statut PAID)
        $totalRevenueTickets = Reservation::where('payment_status', 'PAID')
            ->sum('amount');

        // Chiffre d'affaires des colis
        $totalRevenueParcels = Parcel::sum('price');

        $totalRevenue = $totalRevenueTickets + $totalRevenueParcels;
        $reservationsCount = Reservation::count();

        return response()->json([
            'total_revenue'       => $totalRevenue,
            'total_revenue_fcfa'  => $totalRevenue, // Alias pour le front-end
            'companies_count'     => Company::count(),
            'total_companies'     => Company::count(), // Alias pour le front-end
            'reservations_count'  => $reservationsCount,
            'total_bookings'      => $reservationsCount, // Alias pour le front-end
            'users_count'         => User::count(),
            'total_users'         => User::count(), // Alias pour le front-end
            'parcels_count'       => Parcel::count(),
            'total_parcels'       => Parcel::count(), // Alias pour le front-end
            'buses_count'         => Bus::count(),
            'active_companies'    => Company::where('is_active', true)->count(),
        ]);
    }

    /**
     * 2. Opérations récentes (Dernières réservations & derniers colis)
     */
    public function recentOperations()
    {
        // Récupération des 5 dernières réservations
        $reservations = Reservation::with(['user', 'company'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($reservation) {
                return [
                    'id'          => $reservation->id,
                    'type'        => 'ticket',
                    'reference'   => $reservation->ticket_number ?? ('TKT-' . $reservation->id),
                    'user_name'   => $reservation->passenger_name ?? ($reservation->user->name ?? 'Client'),
                    'price'       => $reservation->amount ?? 0,
                    'created_at'  => $reservation->created_at,
                ];
            });

        // Récupération des 5 derniers colis
        $parcels = Parcel::with(['sender'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($parcel) {
                return [
                    'id'            => $parcel->id,
                    'type'          => 'colis',
                    'tracking_code' => $parcel->tracking_code,
                    'sender_name'   => $parcel->sender_name ?? ($parcel->sender->name ?? 'Inconnu'),
                    'shipping_cost' => $parcel->shipping_cost ?? $parcel->price ?? 0,
                    'price'         => $parcel->price ?? $parcel->shipping_cost ?? 0,
                    'created_at'    => $parcel->created_at,
                ];
            });

        // Fusion et tri chronologique décroissant
        $merged = $reservations->concat($parcels)
            ->sortByDesc('created_at')
            ->values()
            ->take(5);

        return response()->json($merged);
    }

    /**
     * 3. Liste de tous les bus de la plateforme
     */
    public function allBuses()
    {
        return response()->json(
            Bus::with(['company'])
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    /**
     * 4. Voir tous les utilisateurs
     */
    public function allUsers()
    {
        return response()->json(
            User::with(['roles', 'company'])
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    /**
     * 5. Voir tous les colis
     */
    public function allParcels()
    {
        return response()->json(
            Parcel::with(['company', 'sender', 'departureStation', 'arrivalStation'])
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    /**
     * 6. Voir toutes les réservations de billets
     */
    public function allBookings()
    {
        return response()->json(
            Reservation::with(['user', 'company', 'route', 'departureStation', 'destinationStation'])
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }
}
