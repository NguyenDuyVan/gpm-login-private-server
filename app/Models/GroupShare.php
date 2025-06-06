<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupShare extends Model
{
    use HasFactory;

    protected $table = 'group_shares';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'group_id',
        'user_id',
        'role',
    ];

    /**
     * Role constants
     */
    const ROLE_FULL = 'FULL';
    const ROLE_EDIT = 'EDIT';
    const ROLE_VIEW = 'VIEW';

    /**
     * Group this share belongs to
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * User this share is for
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user has full access
     */
    public function hasFullAccess(): bool
    {
        return $this->role === self::ROLE_FULL;
    }

    /**
     * Check if user has edit access
     */
    public function hasEditAccess(): bool
    {
        return in_array($this->role, [self::ROLE_FULL, self::ROLE_EDIT]);
    }

    /**
     * Check if user has view access
     */
    public function hasViewAccess(): bool
    {
        return in_array($this->role, [self::ROLE_FULL, self::ROLE_EDIT, self::ROLE_VIEW]);
    }
}
