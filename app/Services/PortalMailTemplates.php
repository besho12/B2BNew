<?php

namespace App\Services;

use App\Models\PortalMailTemplate;

class PortalMailTemplates
{
    public const DEFAULTS = [
        'ticket_reply' => [
            'name' => 'Support case reply',
            'description' => 'Sent when a team member replies publicly to a support case.',
            'subject' => 'Update on your support case: {{SUMMARY}}',
            'body' => "Hello {{USER_NAME}},\n\nThere is an update to your support case {{CASE_REFERENCE}}.\n\n{{CURRENT_REPLY}}\n\nCurrent status: {{STATUS}}\n\nRegards,\n{{PORTAL_NAME}}",
            'placeholders' => ['USER_NAME', 'SUMMARY', 'CASE_REFERENCE', 'CURRENT_REPLY', 'REPLIES', 'DESCRIPTION', 'STATUS', 'STRING_TOKEN', 'PORTAL_NAME'],
        ],
        'user_invitation' => [
            'name' => 'Workspace invitation',
            'description' => 'Sent when a person is invited to join a customer workspace.',
            'subject' => 'You are invited to join {{COMPANY_NAME}}',
            'body' => "Hello {{USER_NAME}},\n\n{{INVITER_NAME}} invited you to join {{COMPANY_NAME}} as a {{ROLE}}.\n\nThis invitation expires on {{EXPIRY_DATE}}. If you were not expecting it, you can ignore this message.",
            'placeholders' => ['USER_NAME', 'COMPANY_NAME', 'INVITER_NAME', 'ROLE', 'ACCEPT_URL', 'EXPIRY_DATE'],
        ],
        'verify_email' => [
            'name' => 'Email verification',
            'description' => 'Sent after registration to verify a new account email address.',
            'subject' => 'Verify your email address',
            'body' => "Hello {{USER_NAME}},\n\nPlease verify your email address to activate your account. This link expires in 60 minutes.",
            'placeholders' => ['USER_NAME', 'URL'],
        ],
    ];

    public function all(): array
    {
        $saved = PortalMailTemplate::query()->get()->keyBy('template_key');
        return collect(self::DEFAULTS)->map(function (array $template, string $key) use ($saved) {
            $record = $saved->get($key);
            return [
                'key' => $key,
                'name' => $template['name'],
                'description' => $template['description'],
                'subject' => $record?->subject ?? $template['subject'],
                'body' => $record?->body ?? $template['body'],
                'placeholders' => $template['placeholders'],
                'is_customized' => (bool) $record,
            ];
        })->values()->all();
    }

    public function get(string $key): array
    {
        $default = self::DEFAULTS[$key] ?? self::DEFAULTS['ticket_reply'];
        $saved = PortalMailTemplate::query()->where('template_key', $key)->first();
        return ['subject' => $saved?->subject ?? $default['subject'], 'body' => $saved?->body ?? $default['body']];
    }

    public function render(string $template, array $values): string
    {
        return preg_replace_callback('/\{\{([A-Z_]+)\}\}/', fn ($match) => (string) ($values[$match[1]] ?? $match[0]), $template) ?? $template;
    }

    public function renderSubject(string $template, array $values): string
    {
        return trim(preg_replace('/[\r\n\x00-\x1F\x7F]+/', ' ', $this->render($template, $values)) ?? '');
    }
}
