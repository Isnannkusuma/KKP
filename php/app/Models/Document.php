<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Document extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'file_path',
        'file_type',
        'file_size',
        'category_id',
        'access_level',
        'status',
        'error_message',
        'total_chunks',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_chunks' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    // Accessor untuk format file size
    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    // Scope untuk filter berdasarkan status
    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeProcessing($query)
    {
        return $query->whereIn('status', ['pending', 'extracting', 'cleaning', 'embedding']);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
