<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'quarry_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the extractions submitted by this operator.
     */
    public function extractions()
    {
        return $this->hasMany(Extraction::class, 'operator_id');
    }

    /**
     * Get the verifications performed by this checkpoint user.
     */
    public function verifications()
    {
        return $this->hasMany(Extraction::class, 'verified_by');
    }

    /**
     * Get the notifications for this user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the quarries created by this user.
     */
    public function quarries()
    {
        return $this->hasMany(Quarry::class, 'created_by');
    }

    /**
     * Get the quarry assigned to this operator.
     */
    public function quarry()
    {
        return $this->belongsTo(Quarry::class, 'quarry_id');
    }
}
