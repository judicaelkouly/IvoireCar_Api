<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Departure extends Model
{
    protected $fillable = [
    'company_id',
    'code',
    'route_id',
    'departure_station_id',
    'bus_id',
    'driver_id',
    'departure_time',
    'available_seats',
    'status',
    'is_active',
];


    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    // Relation vers la gare de départ
    public function departureStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'departure_station_id');
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
    protected $casts = [
    'is_active' => 'boolean', // <--- Cast automatique en boolean pour React
];
}
