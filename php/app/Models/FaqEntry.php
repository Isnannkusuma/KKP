<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqEntry extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'cluster_label',
        'source_count',
        'question',
        'answer',
        'status',
        'source',
        'source_document_id',
        'search_audit_log_id',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'source_count' => 'integer',
        'cluster_label' => 'integer',
    ];

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(FaqSourceDocument::class, 'source_document_id');
    }
}
