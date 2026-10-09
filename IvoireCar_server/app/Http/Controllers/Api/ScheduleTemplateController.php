<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduleTemplate;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScheduleTemplateController extends Controller
{
    /**
     * Formate la liste des horaires pour inclure le libellé type "1er Départ (06h30)"
     */
    private function formatTemplates($templates)
    {
        return $templates->map(function ($template, $index) {
            $rank = $index + 1;
            $suffix = ($rank === 1) ? 'er' : 'ème';

            // Format de l'heure : HHhMM (ex: 06h30)
            $formattedTime = Carbon::parse($template->departure_time)->format('H\h i');

            // Ajout des attributs dynamiques
            $template->departure_number = $rank;
            $template->formatted_label = "{$rank}{$suffix} Départ ({$formattedTime})";

            return $template;
        });
    }

    /**
     * Liste des horaires récurrents programmés par la compagnie
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $templates = ScheduleTemplate::where('company_id', $companyId)
            ->with(['route.stops', 'departureStation', 'bus', 'driver'])
            ->orderBy('departure_time', 'asc') // Indispensable pour l'ordre des départs
            ->get();

        return response()->json($this->formatTemplates($templates));
    }

    /**
     * Afficher un horaire récurrent spécifique
     */
    public function show(Request $request, ScheduleTemplate $scheduleTemplate)
    {
        // Vérification de la propriété par la compagnie
        if ($scheduleTemplate->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        // Chargement des relations associées
        $scheduleTemplate->load(['route.stops', 'departureStation', 'bus', 'driver']);

        return response()->json($scheduleTemplate);
    }

    /**
     * Récupérer les départs programmés pour une compagnie ou une ligne spécifique (Public / API Modal)
     */
    public function getByRouteOrCompany(Request $request)
    {
        $companyId = $request->query('company_id') ?? $request->user()?->company_id;
        $routeId = $request->query('route_id');
        $departureStationId = $request->query('departure_station_id');

        $query = ScheduleTemplate::where('is_active', true);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($routeId) {
            $query->where('route_id', $routeId);
        }

        if ($departureStationId) {
            $query->where('departure_station_id', $departureStationId);
        }

        $templates = $query->orderBy('departure_time', 'asc')->get();

        return response()->json($this->formatTemplates($templates));
    }

    /**
     * Créer un nouvel horaire récurrent
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        if (!$admin || !$admin->company_id) {
            return response()->json([
                'message' => 'Vous devez appartenir à une compagnie pour effectuer cette action.'
            ], 403);
        }

        $validated = $request->validate([
            'route_id'             => 'required|exists:routes,id',
            'departure_station_id' => 'required|exists:stations,id',
            'bus_id'               => 'nullable|exists:buses,id',
            'driver_id'            => 'nullable|exists:users,id',
            'departure_time'       => 'required|date_format:H:i',
            'days_of_week'         => 'nullable|array',
            'days_of_week.*'       => 'integer|between:1,7',
        ], [
            'route_id.required'             => 'La ligne de transport est obligatoire.',
            'departure_station_id.required' => 'La gare de départ est obligatoire.',
            'departure_time.required'       => 'L\'heure de départ est obligatoire.',
            'departure_time.date_format'    => 'L\'heure de départ doit être au format HH:MM (ex: 06:30).',
        ]);

        $template = ScheduleTemplate::create([
            'company_id'           => $admin->company_id,
            'route_id'             => $validated['route_id'],
            'departure_station_id' => $validated['departure_station_id'],
            'bus_id'               => $validated['bus_id'] ?? null,
            'driver_id'            => $validated['driver_id'] ?? null,
            'departure_time'       => $validated['departure_time'],
            'days_of_week'         => $validated['days_of_week'] ?? [1, 2, 3, 4, 5, 6, 7],
            'is_active'            => true,
        ]);

        return response()->json([
            'message'           => 'Programme récurrent créé avec succès !',
            'schedule_template' => $template->load(['route', 'departureStation', 'bus', 'driver']),
        ], 201);
    }

    /**
     * Mettre à jour un horaire récurrent
     */
    public function update(Request $request, ScheduleTemplate $scheduleTemplate)
    {
        if ($scheduleTemplate->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $validated = $request->validate([
            'route_id'             => 'sometimes|required|exists:routes,id',
            'departure_station_id' => 'sometimes|required|exists:stations,id',
            'bus_id'               => 'nullable|exists:buses,id',
            'driver_id'            => 'nullable|exists:users,id',
            'departure_time'       => 'sometimes|required|date_format:H:i:s,H:i',
            'days_of_week'         => 'nullable|array',
            'days_of_week.*'       => 'integer|between:1,7',
            'is_active'            => 'sometimes|boolean',
        ]);

        $scheduleTemplate->update($validated);

        return response()->json([
            'message'           => 'Programme récurrent mis à jour !',
            'schedule_template' => $scheduleTemplate->fresh()->load(['route', 'departureStation', 'bus', 'driver']),
        ]);
    }

    /**
     * Activer / Désactiver un programme récurrent
     */
    public function toggleActive(Request $request, ScheduleTemplate $scheduleTemplate)
    {
        if ($scheduleTemplate->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $scheduleTemplate->is_active = !$scheduleTemplate->is_active;
        $scheduleTemplate->save();

        return response()->json([
            'message'   => $scheduleTemplate->is_active ? 'Programme activé avec succès.' : 'Programme désactivé avec succès.',
            'is_active' => $scheduleTemplate->is_active,
        ]);
    }

    /**
     * Supprimer un programme récurrent
     */
    public function destroy(Request $request, ScheduleTemplate $scheduleTemplate)
    {
        if ($scheduleTemplate->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $scheduleTemplate->delete();

        return response()->json(['message' => 'Programme récurrent supprimé avec succès.']);
    }
}
