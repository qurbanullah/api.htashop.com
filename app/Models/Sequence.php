<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Sequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequenceable_type',
        'sequenceable_id',
        'year',
        'last_number',
        'prefix',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_number' => 'integer',
    ];

    public function sequenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
