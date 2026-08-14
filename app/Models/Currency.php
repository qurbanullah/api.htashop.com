<?php

namespace App\Models;


use App\Models\Price;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
        use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'precision',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
