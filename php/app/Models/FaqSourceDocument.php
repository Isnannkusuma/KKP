<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaqSourceDocument extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'file_path',
        'file_type',
        'file_size',
        'status',
        'faq_count',
        'error_message',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'faq_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function faqEntries(): HasMany
    {
        return $this->hasMany(FaqEntry::class, 'source_document_id');
    }
}
