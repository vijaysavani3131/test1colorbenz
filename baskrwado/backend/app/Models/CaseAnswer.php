<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseAnswer extends Model
{
    protected $fillable = ['case_record_id', 'key', 'value'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_record_id');
    }
}
