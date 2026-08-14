<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Audit extends Model
{
        use HasFactory;

    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_type_name',
        'meta',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected $casts = [
        'meta' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Backwards-compatible alias expected by some controllers/frontend
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getActorUserIdAttribute()
    {
        return $this->user_id;
    }

    public function getTargetIdAttribute()
    {
        return $this->auditable_id;
    }

    /**
     * Polymorphic relation to the audited model
     */
    public function auditable()
    {
        return $this->morphTo(null, 'auditable_type', 'auditable_id');
    }
}
