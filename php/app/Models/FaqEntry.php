<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqEntry extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'cluster_label',
        'source_count',
        'question',
        'answer',
        'status',
        'source',
        'search_audit_log_id',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'source_count' => 'integer',
        'cluster_label' => 'integer',
    ];
}
