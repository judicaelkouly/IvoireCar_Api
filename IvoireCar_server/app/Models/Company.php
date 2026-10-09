<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    // 1. Ajout de 'images' dans $fillable
    protected $fillable = [
        'name',
        'logo',
        'images',
        'phone',
        'email',
        'is_active'
    ];

    // 2. Cast automatique pour convertir le JSON de la BDD en tableau PHP/JSON
    protected $casts = [
        'images' => 'array',
        'is_active' => 'boolean',
    ];

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
