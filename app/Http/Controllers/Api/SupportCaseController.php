<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SupportCaseReplyMail;
use App\Models\SupportCase;
use App\Models\SupportCaseAttachment;
use App\Models\SupportCaseMessage;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupportCaseController extends Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'waiting', 'resolved', 'closed'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $query = $this->visibleCases($request, $tenant)->with(['creator:id,name', 'assignee:id,name']);
        if ($request->attributes->get('membership')->can('manage_support_cases')) {
            $query->withCount('messages');
        } else {
            $query->withCount(['messages' => fn ($q) => $q->where('is_internal', false)]);
        }
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['priority'])) $query->where('priority', $filters['priority']);
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('subject', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('system_reference', 'like', "%{$search}%"));
        }
        $cases = $query->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'waiting' THEN 2 WHEN 'resolved' THEN 3 ELSE 4 END")
            ->orderByDesc('updated_at')->paginate(20);
        if (! $request->attributes->get('membership')->can('manage_support_cases')) {
            $cases->getCollection()->each(function (SupportCase $case) use ($request) {
                if ((int) $case->created_by !== (int) $request->user()->id) {
                    $case->makeHidden(['feedback_rating', 'feedback_comment', 'feedback_submitted_at', 'feedback_by']);
                }
            });
        }
        return response()->json($cases);
    }

    public function store(Request $request, TenantContext $tenant, AuditLogger $audit)
    {
        abort_unless($request->attributes->get('membership')->can('create_support_cases'), 403);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', Rule::in(['general', 'account', 'product', 'order', 'billing', 'technical', 'other'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'system_reference' => ['nullable', 'string', 'max:120'],
            'support_contract' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'assigned_to' => ['nullable', Rule::exists('company_memberships', 'user_id')->where('company_id', $tenant->id())->where('status', 'active')],
        ]);
        $membership = $request->attributes->get('membership');
        if (! $membership->can('manage_support_cases')) {
            abort_if(isset($data['assigned_to']) && (int) $data['assigned_to'] !== (int) $request->user()->id, 403);
            $data['assigned_to'] = $request->user()->id;
        }
        $case = SupportCase::create([
            'company_id' => $tenant->id(), 'created_by' => $request->user()->id,
            'assigned_to' => $data['assigned_to'] ?? null, 'subject' => $data['subject'],
            'description' => $data['description'], 'category' => $data['category'] ?? 'general',
            'priority' => $data['priority'] ?? 'normal', 'status' => 'open',
            'system_reference' => $data['system_reference'] ?? null,
            'support_contract' => $data['support_contract'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
        ]);
        $audit->log($request, 'support_case.created', $case, [], [
            'subject' => $case->subject, 'category' => $case->category,
            'priority' => $case->priority, 'status' => $case->status,
        ]);
        return response()->json($case->load(['creator:id,name', 'assignee:id,name'])->loadCount('messages'), 201);
    }

    public function show(Request $request, TenantContext $tenant, int $supportCase)
    {
        $case = $this->visibleCases($request, $tenant)->whereKey($supportCase)
            ->with(['creator:id,name', 'assignee:id,name', 'messages', 'attachments'])
            ->firstOrFail();
        $membership = $request->attributes->get('membership');
        if (! $membership->can('manage_support_cases')) {
            $case->setRelation('messages', $case->messages->where('is_internal', false)->values());
        }
        if (! $membership->can('manage_support_cases') && (int) $case->created_by !== (int) $request->user()->id) {
            $case->makeHidden(['feedback_rating', 'feedback_comment', 'feedback_submitted_at', 'feedback_by']);
        }
        $case->setRelation('attachments', $case->attachments->map(fn ($item) => $this->serializeAttachment($item)));
        foreach ($case->messages as $message) {
            $message->setRelation('attachments', $message->attachments->map(fn ($item) => $this->serializeAttachment($item)));
        }
        return response()->json($case);
    }

    public function reply(Request $request, TenantContext $tenant, int $supportCase, AuditLogger $audit)
    {
        abort_unless($request->attributes->get('membership')->can('reply_support_cases'), 403);
        $case = $this->visibleCases($request, $tenant)->whereKey($supportCase)->firstOrFail();
        abort_if(in_array($case->status, ['resolved', 'closed'], true), 409, 'This case is closed. Reopen it before replying.');
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);
        $membership = $request->attributes->get('membership');
        abort_if(($data['is_internal'] ?? false) && ! $membership->can('manage_support_cases'), 403);
        $message = SupportCaseMessage::create([
            'company_id' => $tenant->id(), 'support_case_id' => $case->id,
            'user_id' => $request->user()->id, 'body' => $data['body'],
            'is_internal' => (bool) ($data['is_internal'] ?? false),
        ]);
        $case->touch();
        $audit->log($request, 'support_case.replied', $case, [], ['message_id' => $message->id, 'is_internal' => $message->is_internal]);
        if (! $message->is_internal && (int) $case->created_by !== (int) $request->user()->id) {
            $recipient = $case->contact_email ?: $case->creator()->value('email');
            if ($recipient) {
                try {
                    Mail::to($recipient)->send(new SupportCaseReplyMail($case->loadMissing('creator'), $message));
                } catch (\Throwable $exception) {
                    Log::warning('Support case reply notification could not be sent.', ['support_case_id' => $case->id, 'exception' => $exception::class]);
                }
            }
        }
        return response()->json($message->load(['author', 'attachments']), 201);
    }

    public function uploadAttachment(Request $request, TenantContext $tenant, int $supportCase, AuditLogger $audit)
    {
        abort_unless($request->attributes->get('membership')->can('reply_support_cases'), 403);
        $case = $this->visibleCases($request, $tenant)->whereKey($supportCase)->firstOrFail();
        abort_if(in_array($case->status, ['resolved', 'closed'], true), 409, 'This case is closed.');
        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx', 'max:10240'],
            'message_id' => ['nullable', 'integer'],
        ]);
        abort_if(SupportCaseAttachment::where('company_id', $tenant->id())->where('support_case_id', $case->id)->count() >= 20, 422, 'A case can have up to 20 attachments.');
        $message = null;
        if (! empty($data['message_id'])) {
            $message = SupportCaseMessage::where('company_id', $tenant->id())
                ->where('support_case_id', $case->id)->findOrFail($data['message_id']);
            abort_if($message->is_internal && ! $request->attributes->get('membership')->can('manage_support_cases'), 403);
        }
        $file = $data['attachment'];
        $filename = Str::uuid().'.'.$file->extension();
        $path = $file->storeAs("companies/{$tenant->id()}/support-cases/{$case->id}", $filename, 'private');
        $attachment = SupportCaseAttachment::create([
            'company_id' => $tenant->id(), 'support_case_id' => $case->id,
            'support_case_message_id' => $message?->id, 'uploaded_by' => $request->user()->id,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'storage_path' => $path, 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(),
        ]);
        $audit->log($request, 'support_case.attachment_uploaded', $attachment, [], [
            'support_case_id' => $case->id, 'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes,
        ]);
        return response()->json($this->serializeAttachment($attachment), 201);
    }

    public function downloadAttachment(Request $request, TenantContext $tenant, int $supportCase, int $attachment)
    {
        $case = $this->visibleCases($request, $tenant)->whereKey($supportCase)->firstOrFail();
        $recordQuery = SupportCaseAttachment::where('company_id', $tenant->id())
            ->where('support_case_id', $case->id);
        if (! $request->attributes->get('membership')->can('manage_support_cases')) {
            $recordQuery->where(fn ($query) => $query->whereNull('support_case_message_id')
                ->orWhereHas('message', fn ($message) => $message->where('is_internal', false)));
        }
        $record = $recordQuery->findOrFail($attachment);

        return Storage::disk('private')->download($record->storage_path, $record->original_name, [
            'Content-Type' => $record->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function feedback(Request $request, TenantContext $tenant, int $supportCase, AuditLogger $audit)
    {
        $case = SupportCase::where('company_id', $tenant->id())->whereKey($supportCase)->firstOrFail();
        abort_unless((int) $case->created_by === (int) $request->user()->id, 403);
        abort_unless(in_array($case->status, ['resolved', 'closed'], true), 409, 'Feedback is available after the case has been resolved.');
        abort_if($case->feedback_submitted_at, 409, 'Feedback has already been submitted for this case.');
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:3000'],
        ]);
        $updated = SupportCase::where('company_id', $tenant->id())
            ->whereKey($case->id)->whereNull('feedback_submitted_at')
            ->update([
                'feedback_rating' => $data['rating'], 'feedback_comment' => $data['comment'] ?? null,
                'feedback_submitted_at' => now(), 'feedback_by' => $request->user()->id,
            ]);
        abort_if($updated !== 1, 409, 'Feedback has already been submitted for this case.');
        $case->refresh();
        $audit->log($request, 'support_case.feedback_submitted', $case, [], ['rating' => $case->feedback_rating]);
        return response()->json($case->fresh());
    }

    public function update(Request $request, TenantContext $tenant, int $supportCase, AuditLogger $audit)
    {
        abort_unless($request->attributes->get('membership')->can('manage_support_cases'), 403);
        $case = SupportCase::where('company_id', $tenant->id())->whereKey($supportCase)->firstOrFail();
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'in_progress', 'waiting', 'resolved', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['sometimes', 'nullable', Rule::exists('company_memberships', 'user_id')->where('company_id', $tenant->id())->where('status', 'active')],
        ]);
        $old = $case->only(array_keys($data));
        $case->fill($data);
        if (isset($data['status'])) $case->resolved_at = in_array($data['status'], ['resolved', 'closed'], true) ? ($case->resolved_at ?? now()) : null;
        $case->save();
        $audit->log($request, 'support_case.updated', $case, $old, $case->only(array_keys($data)));
        return response()->json($case->load(['creator:id,name', 'assignee:id,name']));
    }

    private function visibleCases(Request $request, TenantContext $tenant)
    {
        $membership = $request->attributes->get('membership');
        abort_unless($membership->can('view_all_support_cases') || $membership->can('view_own_support_cases'), 403);
        $query = SupportCase::where('company_id', $tenant->id());
        if (! $membership->can('view_all_support_cases')) {
            $query->where(fn ($q) => $q->where('created_by', $request->user()->id)->orWhere('assigned_to', $request->user()->id));
        }
        return $query;
    }

    private function serializeAttachment(SupportCaseAttachment $attachment): array
    {
        return [
            'id' => $attachment->id, 'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes,
            'uploaded_at' => $attachment->created_at,
            'url' => route('support-cases.attachments.download', [
                'supportCase' => $attachment->support_case_id, 'attachment' => $attachment->id,
            ]),
        ];
    }
}
