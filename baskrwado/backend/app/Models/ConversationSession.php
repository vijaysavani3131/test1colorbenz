<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationSession extends Model
{
    protected $fillable = [
        'case_record_id', 'channel', 'external_id', 'locale', 'state',
        'current_question_key', 'context', 'last_message_at',
    ];

    protected $casts = ['context' => 'array', 'last_message_at' => 'datetime'];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }
}
