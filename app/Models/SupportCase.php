<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCase extends Model
{
    protected $fillable = [
        'company_id', 'created_by', 'assigned_to', 'subject', 'description', 'category',
        'priority', 'status', 'resolved_at', 'system_reference', 'support_contract',
        'serial_number', 'contact_person', 'contact_email', 'contact_phone',
        'feedback_rating', 'feedback_comment', 'feedback_submitted_at', 'feedback_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'feedback_submitted_at' => 'datetime',
        'feedback_rating' => 'integer',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function feedbackAuthor() { return $this->belongsTo(User::class, 'feedback_by'); }
    public function messages() { return $this->hasMany(SupportCaseMessage::class)->where('company_id', $this->company_id)->with(['author', 'attachments'])->orderBy('created_at'); }
    public function attachments() { return $this->hasMany(SupportCaseAttachment::class)->where('company_id', $this->company_id)->whereNull('support_case_message_id')->orderBy('created_at'); }
}