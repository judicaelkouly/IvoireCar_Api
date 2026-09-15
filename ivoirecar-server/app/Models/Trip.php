<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'route_id',
        'vehicle_id',
        'driver_id',
        'departure_time',
        'actual_departure_time',
        'actual_arrival_time',
        'current_latitude',
        'current_longitude',
        'status',
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'actual_departure_time' => 'datetime',
        'actual_arrival_time' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
