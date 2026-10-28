<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Les champs pouvant être remplis via le formulaire (Mass Assignment).
     *
     * @var array<int, string>
     *
     */
    protected $appends = ['role'];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'role',
        'password',
        'company_id',
        'station_id',
    ];

    /**
     * Les champs masqués lors de la sérialisation JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Transtypage des attributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getRoleAttribute()
    {
        return $this->getRoleNames()->first();
    }

    /**
     * Relation avec la compagnie de l'utilisateur.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'station_id');
    }
}
