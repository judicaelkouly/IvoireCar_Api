<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Departure;
use App\Models\ScheduleTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DepartureController extends Controller
{
    /**
     * Recherche de départs pour le public / clients
     * Gère la gare de départ spécifique, l'escale/destination, la date, le créneau horaire
     * et instancie automatiquement les départs récurrents.
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id'   => 'required|exists:stations,id',
            'departure_date'       => 'required|date_format:Y-m-d',
            'company_id'           => 'nullable|exists:companies,id',
            'time_slot'            => 'nullable|string|in:Matin,Apres-midi,Soir',
        ]);

        $date = $validated['departure_date'];
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso; // 1 (Lundi) à 7 (Dimanche)

        // 1. Instancier à la volée les départs récurrents pour cette date si non encore générés
        $this->generateDeparturesForDate($date, $dayOfWeek, $request->company_id);

        // 2. Requête de filtrage des départs disponibles
        $query = Departure::where('departure_date', $date)
            ->where('is_active', true)
            ->where('status', 'scheduled')
            ->where('departure_station_id', $validated['departure_station_id'])
            ->whereHas('route.stops', function ($q) use ($validated) {
                // Vérifie que la destination ou l'escale demandée fait partie de la ligne
                $q->where('station_id', $validated['arrival_station_id']);
            });

        // Filtre optionnel par compagnie
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filtre optionnel par créneau horaire
        if ($request->filled('time_slot')) {
            switch ($request->time_slot) {
                case 'Matin':
                    $query->whereBetween('departure_time', ['05:00:00', '11:59:59']);
                    break;
                case 'Apres-midi':
                    $query->whereBetween('departure_time', ['12:00:00', '17:59:59']);
                    break;
                case 'Soir':
                    $query->whereBetween('departure_time', ['18:00:00', '23:59:59']);
                    break;
            }
        }

        $departures = $query->with([
            'company',
            'route.stops.station',
            'departureStation',
            'bus'
        ])
        ->orderBy('departure_time', 'asc')
        ->get();

        return response()->json($departures);
    }

    /**
     * Liste des départs de la compagnie (Vue Admin Back-Office)
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $departures = Departure::where('company_id', $companyId)
            ->with([
                'route',
                'departureStation',
                'bus',
                'driver'
            ])
            ->latest('departure_date')
            ->latest('departure_time')
            ->get();

        return response()->json($departures);
    }

    /**
     * Afficher un départ spécifique
     */
    public function show(Request $request, Departure $departure)
    {
        if ($departure->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        return response()->json(
            $departure->load(['route.stops', 'departureStation', 'bus', 'driver'])
        );
    }

    /**
     * Planifier un départ ponctuel/exceptionnel manuellement par la compagnie
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        if (!$admin->company_id) {
            return response()->json([
                'message' => 'Vous devez appartenir à une compagnie pour planifier un départ.'
            ], 403);
        }

        $validated = $request->validate([
            'route_id'             => 'required|exists:routes,id',
            'departure_station_id' => 'required|exists:stations,id',
            'bus_id'               => 'required|exists:buses,id',
            'driver_id'            => 'required|exists:users,id',
            'departure_date'       => 'required|date|after_or_equal:today',
            'departure_time'       => 'required|date_format:H:i,H:i:s',
        ], [
            'departure_date.required'       => 'La date de départ est obligatoire.',
            'departure_date.after_or_equal' => 'La date de départ doit être aujourd\'hui ou ultérieure.',
            'departure_time.required'       => 'L\'heure de départ est obligatoire.',
            'departure_time.date_format'    => 'L\'heure de départ est invalide.',
            'route_id.required'             => 'La ligne de transport est obligatoire.',
            'bus_id.required'               => 'Le véhicule est obligatoire.',
            'driver_id.required'            => 'Le chauffeur est obligatoire.',
        ]);

        $bus = Bus::findOrFail($validated['bus_id']);
        $code = $this->generateUniqueCode($validated['departure_date']);

        // Formatage pour PostgreSQL au cas où 'departure_time' est un champ Timestamp/DateTime dans la base
        $timeValue = (strlen($validated['departure_time']) === 5)
            ? $validated['departure_time'] . ':00'
            : $validated['departure_time'];

        $departureDateTime = $validated['departure_date'] . ' ' . $timeValue;

        $departure = Departure::create([
            'company_id'           => $admin->company_id,
            'code'                 => $code,
            'route_id'             => $validated['route_id'],
            'departure_station_id' => $validated['departure_station_id'],
            'bus_id'               => $validated['bus_id'],
            'driver_id'            => $validated['driver_id'],
            'departure_date'       => $validated['departure_date'],
            'departure_time'       => $departureDateTime,
            'available_seats'      => $bus->capacity,
            'status'               => 'scheduled',
            'is_active'            => true,
        ]);

        return response()->json([
            'message'   => 'Départ ponctuel planifié avec succès !',
            'departure' => $departure->load(['route', 'departureStation', 'bus', 'driver']),
        ], 201);
    }

    /**
     * Activer / Désactiver un départ
     */
    public function toggleActive(Request $request, Departure $departure)
    {
        if ($departure->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $departure->is_active = !$departure->is_active;
        $departure->save();

        return response()->json([
            'message'   => $departure->is_active ? 'Départ activé avec succès.' : 'Départ désactivé avec succès.',
            'is_active' => $departure->is_active,
            'departure' => $departure
        ]);
    }

    /**
     * Mettre à jour un départ existant
     */
    public function update(Request $request, Departure $departure)
    {
        $admin = $request->user();

        if ($departure->company_id !== $admin->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if ($departure->status === 'cancelled') {
            return response()->json([
                'message' => 'Impossible de modifier un départ qui a été annulé.'
            ], 422);
        }

        $validated = $request->validate([
            'route_id'             => 'sometimes|required|exists:routes,id',
            'departure_station_id' => 'sometimes|required|exists:stations,id',
            'bus_id'               => 'sometimes|required|exists:buses,id',
            'driver_id'            => 'sometimes|required|exists:users,id',
            'departure_date'       => 'sometimes|required|date',
            'departure_time'       => 'sometimes|required',
            'status'               => 'sometimes|required|in:scheduled,in_transit,completed,cancelled',
            'is_active'            => 'sometimes|boolean',
        ]);

        if (isset($validated['bus_id']) && $validated['bus_id'] != $departure->bus_id) {
            $bus = Bus::findOrFail($validated['bus_id']);
            $validated['available_seats'] = $bus->capacity;
        }

        if (isset($validated['departure_time']) && isset($validated['departure_date'])) {
            $timeValue = (strlen($validated['departure_time']) === 5)
                ? $validated['departure_time'] . ':00'
                : $validated['departure_time'];
            $validated['departure_time'] = $validated['departure_date'] . ' ' . $timeValue;
        }

        $departure->update($validated);

        return response()->json([
            'message'   => 'Départ mis à jour avec succès !',
            'departure' => $departure->fresh()->load(['route', 'departureStation', 'bus', 'driver']),
        ], 200);
    }

    /**
     * Annuler un départ
     */
    public function destroy(Request $request, Departure $departure)
    {
        if ($departure->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $departure->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Départ annulé avec succès.']);
    }

    /**
     * Instancie les modèles récurrents pour une date donnée
     */
    private function generateDeparturesForDate(string $date, int $dayOfWeek, ?int $companyId = null)
    {
        $query = ScheduleTemplate::where('is_active', true);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $templates = $query->get();

        foreach ($templates as $template) {
            // Vérifier si le template s'applique au jour de la semaine
            if ($template->days_of_week && !in_array($dayOfWeek, $template->days_of_week)) {
                continue;
            }

            // Éviter de recréer les départs déjà générés
            $exists = Departure::where('schedule_template_id', $template->id)
                ->where('departure_date', $date)
                ->exists();

            if (!$exists) {
                $bus = $template->bus_id ? Bus::find($template->bus_id) : null;
                $capacity = $bus ? $bus->capacity : 50;

                $timeValue = (strlen($template->departure_time) === 5)
                    ? $template->departure_time . ':00'
                    : $template->departure_time;

                $departureDateTime = $date . ' ' . $timeValue;

                Departure::create([
                    'company_id'           => $template->company_id,
                    'schedule_template_id' => $template->id,
                    'code'                 => $this->generateUniqueCode($date),
                    'route_id'             => $template->route_id,
                    'departure_station_id' => $template->departure_station_id,
                    'bus_id'               => $template->bus_id,
                    'driver_id'            => $template->driver_id,
                    'departure_date'       => $date,
                    'departure_time'       => $departureDateTime,
                    'available_seats'      => $capacity,
                    'status'               => 'scheduled',
                    'is_active'            => true,
                ]);
            }
        }
    }

    /**
     * Génère un code unique pour le départ
     */
    private function generateUniqueCode(string $date): string
    {
        do {
            $datePrefix = str_replace('-', '', $date);
            $randomString = strtoupper(Str::random(4));
            $code = "DEP-{$datePrefix}-{$randomString}";
        } while (Departure::where('code', $code)->exists());

        return $code;
    }
}
