<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ticket_number',
        'trip_id',
        'user_id',
        'passenger_name',
        'passenger_phone',
        'seat_number',
        'price',
        'commission_amount',
        'qr_code_hash',
        'status',
        'boarded_at',
    ];

    protected $casts = [
        'boarded_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->morphOne(Payment::class, 'payable');
    }
}
