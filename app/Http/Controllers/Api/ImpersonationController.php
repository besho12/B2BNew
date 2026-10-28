<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Request $request, TenantContext $tenant, int $membership, AuditLogger $audit)
    {
        $actor = $request->user();
        $actorMembership = $request->attributes->get('membership');

        abort_unless(in_array($actorMembership?->role, ['owner', 'admin'], true), 403, 'Only workspace owners and admins can sign in as another user.');
        abort_if($request->session()->has('impersonator_user_id'), 409, 'An impersonation session is already active. Return to your account first.');

        $targetMembership = CompanyMembership::where('company_id', $tenant->id())
            ->where('status', 'active')
            ->with('user:id,name,email,is_platform_admin')
            ->findOrFail($membership);

        abort_if($targetMembership->user_id === $actor->id, 422, 'You are already signed in as this user.');
        abort_if($targetMembership->user?->is_platform_admin, 403, 'Platform administrators cannot be impersonated from a workspace.');
        abort_unless($targetMembership->user?->hasVerifiedEmail(), 422, 'This user must verify their email before you can sign in as them.');

        $request->session()->put([
            'impersonator_user_id' => $actor->id,
            'impersonator_company_id' => $tenant->id(),
        ]);
        $request->session()->forget([
            'b2b_portal_accounts', 'b2b_portal_account', 'b2b_portal_accounts_loaded', 'b2b_portal_demo_mode',
        ]);

        $audit->log($request, 'auth.impersonation_started', $targetMembership, [], [
            'actor_user_id' => $actor->id,
            'target_user_id' => $targetMembership->user_id,
        ]);

        Auth::guard('web')->login($targetMembership->user);
        $request->session()->regenerate();

        return response()->json(['message' => 'You are now viewing the workspace as this user.']);
    }

    public function stop(Request $request, AuditLogger $audit)
    {
        $originalUserId = $request->session()->get('impersonator_user_id');
        abort_unless($originalUserId, 409, 'There is no active impersonation session.');

        $original = User::find($originalUserId);
        $companyId = (int) $request->session()->get('impersonator_company_id');
        $targetMembership = CompanyMembership::where('company_id', $companyId)
            ->where('user_id', $request->user()->id)->first();

        if ($targetMembership) {
            $audit->log($request, 'auth.impersonation_stopped', $targetMembership, [], [
                'actor_user_id' => $originalUserId,
                'impersonated_user_id' => $request->user()->id,
            ]);
        }

        $request->session()->forget(['impersonator_user_id', 'impersonator_company_id']);
        $request->session()->forget([
            'b2b_portal_accounts', 'b2b_portal_account', 'b2b_portal_accounts_loaded', 'b2b_portal_demo_mode',
        ]);

        if (! $original) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return response()->json(['message' => 'The original account is no longer available. Please sign in again.'], 401);
        }

        Auth::guard('web')->login($original);
        $request->session()->regenerate();

        return response()->json(['message' => 'You have returned to your account.']);
    }
}
