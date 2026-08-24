<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'code_alpha3',
        'flag_svg',
    ];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
