<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\Ticket;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['trip.route.departureStation', 'trip.route.arrivalStation', 'user']);

        // Filtrer par trajet
        if ($request->has('trip_id')) {
            $query->where('trip_id', $request->query('trip_id'));
        }

        // Filtrer par statut
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Recherche par référence ou nom du passager
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('passenger_name', 'like', "%{$search}%")
                  ->orWhere('passenger_phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $ticket = DB::transaction(function () use ($validated, $request) {
                // Verrouiller la ligne du trajet pour éviter les race conditions sur les sièges
                $trip = Trip::where('id', $validated['trip_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                // 1. Vérification de la disponibilité des places
                if ($trip->available_seats <= 0) {
                    throw new \Exception('Aucune place disponible pour ce trajet.', 422);
                }

                // 2. Définition du prix payé si non précisé
                $pricePaid = $validated['price_paid'] ?? $trip->price;

                // 3. Génération d'un numéro de billet unique et du code QR
                $ticketNumber = 'TICK-' . strtoupper(Str::random(8));
                $qrCodeData = json_encode([
                    'ticket_number' => $ticketNumber,
                    'trip_id' => $trip->id,
                    'passenger' => $validated['passenger_name'],
                    'phone' => $validated['passenger_phone'],
                ]);

                // 4. Création du billet
                $ticket = Ticket::create([
                    'trip_id' => $trip->id,
                    'user_id' => $request->user()->id ?? null,
                    'passenger_name' => $validated['passenger_name'],
                    'passenger_phone' => $validated['passenger_phone'],
                    'seat_number' => $validated['seat_number'] ?? null,
                    'ticket_number' => $ticketNumber,
                    'qr_code' => $qrCodeData,
                    'pickup_station_id' => $validated['pickup_station_id'] ?? null,
                    'dropoff_station_id' => $validated['dropoff_station_id'] ?? null,
                    'price_paid' => $pricePaid,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'payment_status' => isset($validated['payment_method']) && $validated['payment_method'] !== 'cash' ? 'pending' : 'completed',
                    'status' => 'reserved',
                ]);

                // 5. Décrémentation des places disponibles
                $trip->decrement('available_seats');

                return $ticket;
            });

            return response()->json([
                'message' => 'Billet réservé avec succès.',
                'ticket' => $ticket->load(['trip.route.departureStation', 'trip.route.arrivalStation']),
            ], 201);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() === 422 ? 422 : 500;
            return response()->json([
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json($ticket->load([
            'trip.route.departureStation',
            'trip.route.arrivalStation',
            'trip.vehicle',
            'user',
            'pickupStation',
            'dropoffStation'
        ]));
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $ticket) {
            $oldStatus = $ticket->status;
            $newStatus = $validated['status'];

            // Si le billet est annulé, ré-incrémenter les places disponibles sur le trajet
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                $ticket->trip()->increment('available_seats');
            }

            $ticket->update($validated);
        });

        return response()->json([
            'message' => 'Statut du billet mis à jour avec succès.',
            'ticket' => $ticket,
        ]);
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        DB::transaction(function () use ($ticket) {
            // Remettre en liberté le siège si le billet n'était pas déjà annulé
            if ($ticket->status !== 'cancelled') {
                $ticket->trip()->increment('available_seats');
            }
            $ticket->delete();
        });

        return response()->json([
            'message' => 'Billet supprimé avec succès.',
        ]);
    }
}
