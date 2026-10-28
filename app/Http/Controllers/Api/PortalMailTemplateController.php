<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdminAction;
use App\Models\PortalMailTemplate;
use App\Services\PortalMailTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PortalMailTemplateController extends Controller
{
    public function index(PortalMailTemplates $templates)
    {
        return response()->json(['templates' => $templates->all()]);
    }

    public function update(Request $request, PortalMailTemplates $templates)
    {
        $keys = array_keys(PortalMailTemplates::DEFAULTS);
        $data = $request->validate([
            'templates' => ['required', 'array', 'min:1', 'max:'.count($keys)],
            'templates.*.key' => ['required', 'string', 'in:'.implode(',', $keys)],
            'templates.*.subject' => ['required', 'string', 'max:190'],
            'templates.*.body' => ['required', 'string', 'max:10000'],
        ]);
        $submittedKeys = array_column($data['templates'], 'key');
        if (count(array_unique($submittedKeys)) !== count($submittedKeys)) {
            throw ValidationException::withMessages(['templates' => ['Include each template at most once.']]);
        }
        foreach ($data['templates'] as $item) {
            preg_match_all('/\{\{([A-Z_]+)\}\}/', $item['subject'].' '.$item['body'], $matches);
            $allowed = PortalMailTemplates::DEFAULTS[$item['key']]['placeholders'];
            $unknown = array_diff(array_unique($matches[1]), $allowed);
            if ($unknown) {
                throw ValidationException::withMessages(['templates' => ['Unsupported placeholder(s): '.implode(', ', $unknown).'.']]);
            }
        }

        DB::transaction(function () use ($data, $request) {
            foreach ($data['templates'] as $item) {
                PortalMailTemplate::updateOrCreate(['template_key' => $item['key']], [
                    'subject' => trim($item['subject']), 'body' => trim($item['body']),
                ]);
            }
            PlatformAdminAction::create([
                'admin_user_id' => $request->user()->id,
                'company_id' => null,
                'action' => 'portal_mail_templates_updated',
                'reason' => 'Updated shared portal email templates',
                'details' => ['template_keys' => array_column($data['templates'], 'key')],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Email templates saved.', 'templates' => $templates->all()]);
    }

    public function reset(Request $request, string $key, PortalMailTemplates $templates)
    {
        abort_unless(array_key_exists($key, PortalMailTemplates::DEFAULTS), 404);
        DB::transaction(function () use ($request, $key) {
            PortalMailTemplate::where('template_key', $key)->delete();
            PlatformAdminAction::create([
                'admin_user_id' => $request->user()->id, 'company_id' => null,
                'action' => 'portal_mail_template_reset', 'reason' => 'Restored a default portal email template',
                'details' => ['template_key' => $key], 'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500), 'created_at' => now(),
            ]);
        });
        return response()->json(['message' => 'Default template restored.', 'templates' => $templates->all()]);
    }
}
