<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
        protected $fillable = [
        'name',
        'code',
        'code_alpha3',
        'flag_svg',
    ];
    
    /**
     * Get the CSS class for the flag icon
     */
    public function getFlagCssClassAttribute()
    {
        return 'flag-' . strtolower($this->code);
    }
    
    /**
     * Get the flag image URL
     */
    public function getFlagUrlAttribute()
    {
        return 'https://flagpedia.net/data/flags/w40/' . strtolower($this->code) . '.png';
    }
}
