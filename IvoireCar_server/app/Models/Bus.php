<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bus extends Model
{
    protected $fillable = [
        'registration_number',
        'model',
        'capacity',
        'status',
        'company_id',
        'station_id', // <--- Ajouté pour lier le bus à une gare spécifique
        'user_id', // Chauffeur principal (driver)
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
