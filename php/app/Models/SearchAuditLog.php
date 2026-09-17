<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SearchAuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_type',
        'user_identifier',
        'user_name',
        'query',
        'answer',
        'response_status',
        'feedback',
        'feedback_at',
        'result_count',
        'result_document_ids',
        'access_level_used',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'result_count' => 'integer',
        'result_document_ids' => 'array',
        'feedback_at' => 'datetime',
    ];
}
