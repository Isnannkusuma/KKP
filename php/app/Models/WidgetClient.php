<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WidgetClient extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'widget_key',
        'secret_key',
        'allowed_origins',
        'is_active',
    ];

    protected $casts = [
        'allowed_origins' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'secret_key',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WidgetClient $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}