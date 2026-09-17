<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseEvent extends Model
{
    protected $fillable = ['case_record_id', 'type', 'actor_type', 'actor_id', 'message', 'data'];
    protected $casts = ['data' => 'array'];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class);
    }
}
