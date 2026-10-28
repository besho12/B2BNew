<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCaseMessage extends Model
{
    protected $fillable = ['company_id', 'support_case_id', 'user_id', 'body', 'is_internal'];
    protected $casts = ['is_internal' => 'boolean'];

    public function supportCase() { return $this->belongsTo(SupportCase::class); }
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
    public function attachments() { return $this->hasMany(SupportCaseAttachment::class)->where('company_id', $this->company_id)->orderBy('created_at'); }
}