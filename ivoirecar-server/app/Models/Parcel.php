<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parcel extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
    'company_id',
    'sender_id',
    'sender_name',
    'sender_phone',
    'recipient_name',
    'recipient_phone',
    'from_station_id',
    'to_station_id',
    'tracking_number',
    'description',
    'weight',
    'price',
    'status',
    'receiver_identity_name',
    'receiver_identity_phone',
    'receiver_id_card_number',
    'delivered_at',

    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function departureStation()
    {
        return $this->belongsTo(Station::class, 'departure_station_id');
    }

    public function arrivalStation()
    {
        return $this->belongsTo(Station::class, 'arrival_station_id');
    }
}
