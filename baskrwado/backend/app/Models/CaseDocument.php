<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseDocument extends Model
{
    protected $fillable = [
        'case_record_id', 'category', 'original_name', 'mime_type', 'size_bytes',
        'storage_disk', 'storage_path', 'sha256', 'source', 'status', 'ocr_text',
        'extracted_data', 'verified_by_admin_user_id', 'verified_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'extracted_data' => 'array',
        'verified_at' => 'datetime',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'verified_by_admin_user_id');
    }
}
