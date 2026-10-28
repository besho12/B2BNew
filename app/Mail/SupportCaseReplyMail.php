<?php

namespace App\Mail;

use App\Models\SupportCase;
use App\Models\SupportCaseMessage;
use App\Services\PortalMailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

class SupportCaseReplyMail extends Mailable
{
    use Queueable;

    public function __construct(public SupportCase $supportCase, public SupportCaseMessage $message) {}

    public function build(): self
    {
        $templates = app(PortalMailTemplates::class);
        $template = $templates->get('ticket_reply');
        $values = [
            'USER_NAME' => $this->supportCase->contact_person ?: $this->supportCase->creator?->name ?: 'there',
            'SUMMARY' => $this->supportCase->subject,
            'CASE_REFERENCE' => 'CASE-'.$this->supportCase->id,
            'STRING_TOKEN' => 'CASE-'.$this->supportCase->id,
            'CURRENT_REPLY' => $this->message->body,
            'REPLIES' => $this->message->body,
            'DESCRIPTION' => $this->supportCase->description,
            'STATUS' => str_replace('_', ' ', ucfirst($this->supportCase->status)),
            'PORTAL_NAME' => (string) config('app.name', 'Customer Portal'),
        ];
        return $this->subject($templates->renderSubject($template['subject'], $values))
            ->view('emails.support-case-reply', ['body' => $templates->render($template['body'], $values)]);
    }
}
