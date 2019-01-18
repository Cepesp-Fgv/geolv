<?php

namespace GeoLV;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Class User
 * @package GeoLV
 * @property string name
 * @property string email
 * @property string password
 * @property \Illuminate\Support\Collection|GeocodingFile[] files
 * @property string role
 * @property int id
 * @property Carbon updated_at
 * @property Carbon email_verified_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    const ADMIN_ROLE = 'admin';
    const DEV_ROLE = 'dev';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'role'
    ];

    public function getLastUpdateAttribute()
    {
        $last = $this->files()->orderBy('updated_at', 'desc')->first();
        if (!empty($last))
            return $last->updated_at;
        else
            return $this->updated_at;
    }

    public function files()
    {
        return $this->hasMany(GeocodingFile::class);
    }

    public function isAdmin()
    {
        return ($this->role == static::ADMIN_ROLE) || $this->isDev();
    }

    public function isDev()
    {
        return $this->role == static::DEV_ROLE;
    }
}
