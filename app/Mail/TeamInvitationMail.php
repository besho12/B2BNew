<?php

namespace App\Mail;

use App\Models\CompanyInvitation;
use App\Services\PortalMailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CompanyInvitation $invitation, public string $acceptUrl) {}

    public function build(): self
    {
        $templates = app(PortalMailTemplates::class);
        $template = $templates->get('user_invitation');
        $values = [
            'USER_NAME' => $this->invitation->name,
            'COMPANY_NAME' => $this->invitation->company->name,
            'INVITER_NAME' => $this->invitation->inviter?->name ?? 'A workspace administrator',
            'ROLE' => $this->invitation->role,
            'ACCEPT_URL' => $this->acceptUrl,
            'EXPIRY_DATE' => $this->invitation->expires_at->toDayDateTimeString(),
        ];
        return $this->subject($templates->renderSubject($template['subject'], $values))
            ->view('emails.team-invitation', ['invitation' => $this->invitation, 'acceptUrl' => $this->acceptUrl, 'body' => $templates->render($template['body'], $values)]);
    }
}
