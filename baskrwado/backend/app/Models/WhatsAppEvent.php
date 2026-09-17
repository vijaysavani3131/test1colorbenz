<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppEvent extends Model
{
    protected $table = 'whatsapp_events';

    protected $fillable = [
        'event_key', 'wa_id', 'message_id', 'event_type', 'payload', 'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
