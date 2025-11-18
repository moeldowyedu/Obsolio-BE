<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Engine extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'category',
        'capabilities',
        'input_types',
        'color',
        'icon',
        'is_active',
        'default_rubric',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'input_types' => 'array',
        'is_active' => 'boolean',
        'default_rubric' => 'array',
    ];

    public function rubrics(): HasMany
    {
        return $this->hasMany(EngineRubric::class);
    }
}