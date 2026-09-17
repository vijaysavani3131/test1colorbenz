<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseNote extends Model
{
    protected $fillable = ['case_record_id', 'admin_user_id', 'note', 'customer_visible'];
    protected $casts = ['customer_visible' => 'boolean'];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}
