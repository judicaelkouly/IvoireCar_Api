<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'name',
        'city',
        'address',
        'latitude',
        'phone',
        'longitude',
        'manager_phone',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
