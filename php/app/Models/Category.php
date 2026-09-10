<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
