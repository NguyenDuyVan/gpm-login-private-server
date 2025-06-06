<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proxy extends Model
{
    use HasFactory;

    protected $table = 'proxies';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'host',
        'port',
        'type',
        'username',
        'password',
        'is_active',
        'description',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Proxy type constants
     */
    const TYPE_HTTP = 'HTTP';
    const TYPE_HTTPS = 'HTTPS';
    const TYPE_SOCKS4 = 'SOCKS4';
    const TYPE_SOCKS5 = 'SOCKS5';

    /**
     * Tags associated with this proxy
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'proxy_tags')
            ->withTimestamps();
    }

    /**
     * Get the proxy's full address
     */
    public function getFullAddressAttribute(): string
    {
        return "{$this->host}:{$this->port}";
    }

    /**
     * Get the proxy connection string
     */
    public function getConnectionStringAttribute(): string
    {
        $auth = '';
        if ($this->username && $this->password) {
            $auth = "{$this->username}:{$this->password}@";
        }

        return strtolower($this->type) . "://{$auth}{$this->host}:{$this->port}";
    }

    /**
     * Check if proxy requires authentication
     */
    public function requiresAuth(): bool
    {
        return !empty($this->username) && !empty($this->password);
    }

    /**
     * Scope to get only active proxies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get proxies by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
