<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Curated specification key (e.g. "Operating Voltage") that product editors pick
 * from instead of typing a free-form label.
 */
class Specification extends Model
{
    use HasFactory;

    protected $table = 'specifications';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'group',
        'unit',
        'description',
        'sort_order',
        'usage_count',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'usage_count' => 'integer',
    ];
}
