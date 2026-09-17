<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class AdminUser extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'job_title', 'password', 'role', 'active',
        'timezone', 'notification_preferences', 'last_login_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'active' => 'boolean',
        'last_login_at' => 'datetime',
        'notification_preferences' => 'array',
    ];

    public function tokens(): HasMany
    {
        return $this->hasMany(AdminApiToken::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AdminNotification::class);
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = str_starts_with($value, '$2') || str_starts_with($value, '$argon')
            ? $value
            : Hash::make($value);
    }

    public function canManageStaff(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }
}
