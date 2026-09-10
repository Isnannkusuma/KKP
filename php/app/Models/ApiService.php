<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApiService extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'method',
        'url',
        'inspect_url',
        'headers',
        'query_params',
        'body',
        'request_schema',
        'response_fields',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'encrypted:array',
            'query_params' => 'array',
            'body' => 'array',
            'request_schema' => 'array',
            'response_fields' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
