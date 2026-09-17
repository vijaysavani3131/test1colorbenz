<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    protected $fillable = [
        'conversation_session_id', 'direction', 'external_message_id',
        'message_type', 'text', 'payload', 'status',
    ];

    protected $casts = ['payload' => 'array'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConversationSession::class, 'conversation_session_id');
    }
}
