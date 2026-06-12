<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'bio',
        'avatar',
        'storage_used',
        'storage_quota',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at'      => 'datetime',
        'password'               => 'hashed',
        'two_factor_confirmed_at'=> 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'user_one_id')
            ->orWhere('user_two_id', $this->id);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // how much storage is left
    public function storageRemaining(): int
    {
        return $this->storage_quota - $this->storage_used;
    }

    // percentage used
    public function storagePercentage(): float
    {
        if ($this->storage_quota === 0) return 100;
        return round(($this->storage_used / $this->storage_quota) * 100, 1);
    }

    // formatted used
    public function getStorageUsedFormattedAttribute(): string
    {
        return $this->formatBytes($this->storage_used);
    }

    // formatted quota
    public function getStorageQuotaFormattedAttribute(): string
    {
        return $this->formatBytes($this->storage_quota);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}