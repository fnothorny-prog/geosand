<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Extraction extends Model
{
    use HasFactory;

    /**
     * The actual database table name.
     */
    protected $table = 'extraction_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'quarry_id',
        'operator_id',
        'truck_identifier',
        'reported_quantity',
        'destination',
        'extraction_timestamp',
        'status',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reported_quantity' => 'decimal:2',
            'extraction_timestamp' => 'datetime',
        ];
    }

    /**
     * Get the quarry for this extraction.
     */
    public function quarry()
    {
        return $this->belongsTo(Quarry::class);
    }

    /**
     * Get the operator who submitted this extraction.
     */
    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * Get the checkpoint user who verified this extraction.
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
