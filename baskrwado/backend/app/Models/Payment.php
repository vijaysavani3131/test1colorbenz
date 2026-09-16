<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'case_record_id', 'provider', 'provider_order_id', 'provider_payment_id',
        'amount_paise', 'currency', 'status', 'metadata', 'paid_at',
    ];

    protected $casts = ['metadata' => 'array', 'paid_at' => 'datetime', 'amount_paise' => 'integer'];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class);
    }
}
