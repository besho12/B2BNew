<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCaseAttachment extends Model
{
    protected $fillable = [
        'company_id', 'support_case_id', 'support_case_message_id', 'uploaded_by',
        'original_name', 'storage_path', 'mime_type', 'size_bytes',
    ];

    public function supportCase() { return $this->belongsTo(SupportCase::class); }
    public function message() { return $this->belongsTo(SupportCaseMessage::class, 'support_case_message_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}