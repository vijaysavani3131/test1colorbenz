<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CaseRecord extends Model
{
    protected $fillable = [
        'public_id', 'service_slug', 'source', 'locale', 'name', 'phone', 'email',
        'assigned_admin_user_id', 'status', 'priority', 'payment_status', 'fee_paise',
        'readiness_score', 'summary', 'ai_report', 'metadata', 'last_activity_at',
        'resolved_at', 'closed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'ai_report' => 'array',
        'readiness_score' => 'integer',
        'fee_paise' => 'integer',
        'last_activity_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $appends = ['status_label'];

    protected static function booted(): void
    {
        static::creating(function (CaseRecord $case): void {
            if (!$case->public_id) {
                do {
                    $id = 'BKW-'.Str::upper(Str::random(8));
                } while (static::query()->where('public_id', $id)->exists());
                $case->public_id = $id;
            }

            $case->last_activity_at ??= now();
        });
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CaseAnswer::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class)->latest();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CaseNote::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ConversationSession::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_admin_user_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'intake' => 'Intake started',
            'needs_info' => 'More information needed',
            'ready_for_review' => 'Ready for review',
            'in_progress' => 'In progress',
            'waiting_customer' => 'Waiting for customer',
            'waiting_external' => 'Waiting for response',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => Str::headline((string) $this->status),
        };
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->saveQuietly();
    }
}
