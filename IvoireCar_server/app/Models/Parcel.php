<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parcel extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_code',
        'company_id',
        'sender_id',
        'departure_station_id',
        'arrival_station_id',
        'sender_name',
        'sender_phone',
        'recipient_name',
        'recipient_phone',
        'description',
        'estimated_value',
        'shipping_cost',
        'price',
        'payment_method',
        'weight_kg',
        'status',
        'collector_name',
        'collector_phone',
        'collector_id_number',
        'collected_at',
    ];

    /**
     * Expéditeur (Utilisateur inscrit)
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Gare de départ
     */
    public function departureStation()
    {
        return $this->belongsTo(Station::class, 'departure_station_id');
    }

    /**
     * Gare d'arrivée
     */
    public function arrivalStation()
    {
        return $this->belongsTo(Station::class, 'arrival_station_id');
    }

    /**
     * Compagnie associée
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
