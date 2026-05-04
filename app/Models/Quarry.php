<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quarry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'location',
        'permit_number',
        'permit_expiry_date',
        'latitude',
        'longitude',
        'status',
        'total_area_hectares',
        'contact_person',
        'contact_phone',
        'contact_email',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'total_area_hectares' => 'decimal:2',
            'permit_expiry_date' => 'date',
        ];
    }

    /**
     * Get the extractions for this quarry.
     */
    public function extractions()
    {
        return $this->hasMany(Extraction::class);
    }

    /**
     * Get the documents for this quarry.
     */
    public function documents()
    {
        return $this->hasMany(\App\Models\QuarryDocument::class);
    }

    /**
     * Get the user who created this quarry.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Accessor for backward compatibility - created_by doesn't exist in DB.
     */
    public function getCreatedByAttribute()
    {
        return null;
    }

    /**
     * Get the reports for this quarry.
     */
    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Accessor for location (backward compatibility).
     * Returns location field.
     */
    public function getLocationAttribute($value)
    {
        return $value;
    }

    /**
     * Accessor for address (backward compatibility).
     * Returns location field.
     */
    public function getAddressAttribute()
    {
        return $this->attributes['location'] ?? null;
    }
}
