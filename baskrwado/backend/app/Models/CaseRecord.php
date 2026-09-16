<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CaseRecord extends Model
{
    protected $fillable = [
        'public_id', 'service_slug', 'source', 'locale', 'name', 'phone', 'email',
        'status', 'readiness_score', 'summary', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'readiness_score' => 'integer',
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
        });
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CaseAnswer::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'intake' => 'Intake started',
            'needs_info' => 'More information needed',
            'ready_for_review' => 'Ready for review',
            'in_progress' => 'In progress',
            'waiting_external' => 'Waiting for response',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => Str::headline((string) $this->status),
        };
    }
}
