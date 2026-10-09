<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Enregistrer une nouvelle réservation
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id'             => 'required|exists:companies,id',
            'route_id'               => 'required|exists:routes,id',
            'schedule_template_id'   => 'required|exists:schedule_templates,id',
            'departure_station_id'   => 'required|exists:stations,id',
            'destination_station_id' => 'required|exists:stations,id|different:departure_station_id',
            'booking_date'           => 'required|date',
            'passenger_name'         => 'required|string|max:255',
            'passenger_phone'        => 'required|string|max:20',
            'payment_method'         => 'required|string',
            'amount'                 => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $ticketNumber = 'TKT-' . date('Y') . '-' . strtoupper(Str::random(5));

            $reservation = Reservation::create([
                'ticket_number'          => $ticketNumber,
                'user_id'                => $request->user()?->id,
                'company_id'             => $validated['company_id'],
                'route_id'               => $validated['route_id'],
                'schedule_template_id'   => $validated['schedule_template_id'],
                'departure_station_id'   => $validated['departure_station_id'],
                'destination_station_id' => $validated['destination_station_id'],
                'booking_date'           => $validated['booking_date'],
                'passenger_name'         => $validated['passenger_name'],
                'passenger_phone'        => $validated['passenger_phone'],
                'amount'                 => $validated['amount'],
                'payment_method'         => strtoupper($validated['payment_method']),
                'payment_status'         => 'PAID',
                'qr_code_value'          => json_encode([
                    'ticket'    => $ticketNumber,
                    'passenger' => $validated['passenger_name'],
                    'date'      => $validated['booking_date'],
                ]),
            ]);

            return response()->json([
                'message' => 'Réservation enregistrée avec succès !',
                'data'    => $reservation,
            ], 201);
        });
    }

    /**
     * SUPER ADMIN : Récupérer toutes les réservations avec leurs informations complètes
     */
    public function allReservations(Request $request)
    {
        $reservations = Reservation::with([
            'user:id,name,email,phone',
            'company:id,name,email,phone,logo',
            'departureStation:id,name,city',
            'destinationStation:id,name,city',
            'route:id,name',
            'scheduleTemplate'
        ])
        ->latest()
        ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $reservations,
        ], 200);
    }

    /**
     * COMPANY ADMIN : Récupérer uniquement les réservations liées à la compagnie de l'utilisateur connecté
     */
    public function companyReservations(Request $request)
    {
        $user = $request->user();

        // Récupération du company_id à partir de l'utilisateur ou des paramètres
        $companyId = $user->company_id ?? $request->get('company_id');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune compagnie associée à cet utilisateur.',
            ], 403);
        }

        $reservations = Reservation::with([
            'user:id,name,email,phone',
            'departureStation:id,name,city',
            'destinationStation:id,name,city',
            'route:id,name',
            'scheduleTemplate'
        ])
        ->where('company_id', $companyId)
        ->latest()
        ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $reservations,
        ], 200);
    }
}
