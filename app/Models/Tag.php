<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $table = 'tags';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'color',
        'description',
    ];

    /**
     * Profiles with this tag
     */
    public function profiles()
    {
        return $this->belongsToMany(Profile::class, 'profile_tags')
            ->withTimestamps();
    }

    /**
     * Proxies with this tag
     */
    public function proxies()
    {
        return $this->belongsToMany(Proxy::class, 'proxy_tags')
            ->withTimestamps();
    }

    /**
     * Get the tag's display name with color if available
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ($this->color ? " ({$this->color})" : '');
    }
}
