<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class QuarryDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'quarry_id',
        'uploaded_by',
        'document_type',
        'title',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'notes',
    ];

    protected $appends = ['download_url', 'file_size_formatted'];

    /**
     * Get the quarry this document belongs to.
     */
    public function quarry()
    {
        return $this->belongsTo(Quarry::class);
    }

    /**
     * Get the user who uploaded this document.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the public download URL.
     */
    public function getDownloadUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Get human-readable file size.
     */
    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
