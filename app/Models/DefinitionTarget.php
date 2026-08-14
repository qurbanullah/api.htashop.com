<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefinitionTarget extends Model
{
        use HasFactory;

    protected $fillable = [
        'definition_id',
        'target_type',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(Definition::class);
    }
}
