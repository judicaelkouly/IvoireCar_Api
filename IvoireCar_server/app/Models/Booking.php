<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'company_id',
        'route_id',
        'schedule_template_id',
        'departure_station_id',
        'destination_station_id',
        'booking_date',
        'passenger_name',
        'passenger_phone',
        'seat_number',
        'amount',
        'payment_method',
        'payment_status',
        'qr_code_value',
    ];

    /**
     * Casts pour le formatage des données
     */
    protected $casts = [
        'booking_date' => 'date',
        'amount'       => 'float',
        'seat_number'  => 'integer',
    ];

    /**
     * Relation avec l'utilisateur (passager ou agent ayant créé la réservation)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec la compagnie de transport
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relation avec la ligne de transport (Route)
     */
    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Relation avec l'horaire / programme de départ (ScheduleTemplate)
     */
    public function scheduleTemplate()
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    /**
     * Relation avec la gare de départ
     */
    public function departureStation()
    {
        return $this->belongsTo(Station::class, 'departure_station_id');
    }

    /**
     * Relation avec la gare d'arrivée / destination
     */
    public function destinationStation()
    {
        return $this->belongsTo(Station::class, 'destination_station_id');
    }
}
