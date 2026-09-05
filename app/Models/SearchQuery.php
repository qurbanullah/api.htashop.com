<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchQuery extends Model
{
    protected $fillable = [
        'query',
        'normalized_query',
        'user_id',
        'session_id',
        'source',
        'results_count',
        'is_zero_result',
        'filters',
        'clicked_product_id',
        'clicked_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'results_count' => 'integer',
        'is_zero_result' => 'boolean',
        'filters' => 'array',
        'clicked_product_id' => 'integer',
        'clicked_at' => 'datetime',
    ];
}
