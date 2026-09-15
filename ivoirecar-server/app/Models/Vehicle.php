<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'registration_number',
        'model',
        'capacity_seats',
        'seat_layout',
        'has_wifi',
        'has_ac',
        'status',
    ];

    protected $casts = [
        'seat_layout' => 'array',
        'has_wifi' => 'boolean',
        'has_ac' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
