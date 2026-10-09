<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Route;
use App\Models\ScheduleTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Effectuer une réservation directe / en guichet ou en ligne
     * URL: POST /api/bookings/direct (ou /api/bookings)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id'             => 'required|exists:companies,id',
            'route_id'               => 'required|exists:routes,id',
            'schedule_template_id'   => 'required|exists:schedule_templates,id',
            'departure_station_id'   => 'required|exists:stations,id',
            'destination_station_id' => 'required|exists:stations,id|different:departure_station_id',
            'booking_date'           => 'required|date|after_or_equal:today',
            'passenger_name'         => 'required|string|max:255',
            'passenger_phone'        => 'required|string|max:20',
            'payment_method'         => 'required|string|in:WAVE,ORANGE,MTN,MOOV,STATION',
            'seat_number'            => 'nullable|integer|min:1',
        ]);

        // 1. Charger la ligne avec ses escales
        $route = Route::with(['stops' => function ($q) {
            $q->orderBy('stop_order', 'asc');
        }])->where('id', $validated['route_id'])
          ->where('company_id', $validated['company_id'])
          ->firstOrFail();

        // 2. Calculer le montant exact côté serveur (Sécurité)
        $amount = 0;
        $matchedStop = $route->stops->firstWhere('station_id', (int) $validated['destination_station_id']);

        if ($matchedStop && floatval($matchedStop->price) > 0) {
            $amount = (float) $matchedStop->price;
        } else {
            $amount = (float) ($route->price ?? 0);
        }

        if ($amount <= 0) {
            return response()->json([
                'message' => 'Impossible de déterminer un tarif valide pour ce trajet.'
            ], 422);
        }

        // 3. Traitement au sein d'une transaction
        return DB::transaction(function () use ($validated, $route, $amount, $request) {

            // Génération d'un code unique de ticket (ex: TKT-2026-A89Z2)
            $ticketNumber = 'TKT-' . date('Y') . '-' . strtoupper(Str::random(5));

            $booking = Booking::create([
                'ticket_number'          => $ticketNumber,
                'user_id'                => $request->user()?->id,
                'company_id'             => $validated['company_id'],
                'route_id'               => $route->id,
                'schedule_template_id'   => $validated['schedule_template_id'],
                'departure_station_id'   => $validated['departure_station_id'],
                'destination_station_id' => $validated['destination_station_id'],
                'booking_date'           => $validated['booking_date'],
                'passenger_name'         => $validated['passenger_name'],
                'passenger_phone'        => $validated['passenger_phone'],
                'seat_number'            => $validated['seat_number'] ?? null,
                'amount'                 => $amount,
                'payment_method'         => strtoupper($validated['payment_method']),
                'payment_status'         => strtoupper($validated['payment_method']) === 'STATION' ? 'pending' : 'paid',
                'qr_code_value'          => json_encode([
                    'ticket'    => $ticketNumber,
                    'passenger' => $validated['passenger_name'],
                    'date'      => $validated['booking_date'],
                ]),
            ]);

            return response()->json([
                'message' => 'Réservation enregistrée avec succès !',
                'data'    => $booking->load([
                    'route',
                    'scheduleTemplate',
                    'departureStation',
                    'destinationStation'
                ]),
            ], 201);
        });
    }

    /**
     * Historique des réservations de l'utilisateur connecté
     */
    public function myBookings(Request $request)
    {
        $bookings = Booking::with([
            'route',
            'scheduleTemplate',
            'departureStation',
            'destinationStation'
        ])
        ->where('user_id', $request->user()->id)
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json($bookings);
    }
}
