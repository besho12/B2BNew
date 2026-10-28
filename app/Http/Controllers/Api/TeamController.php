<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TeamInvitationMail;
use App\Models\CompanyInvitation;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        $membership = $request->attributes->get('membership');
        abort_unless($membership->can('view_team') || $membership->can('manage_members'), 403);

        $members = CompanyMembership::where('company_id', $tenant->id())
            ->with('user:id,name,email')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->get()
            ->map(fn (CompanyMembership $membership) => $this->serialize($membership));

        $invitations = CompanyInvitation::where('company_id', $tenant->id())
            ->whereNull('accepted_at')->whereNull('revoked_at')->latest()
            ->get()->map(fn (CompanyInvitation $invitation) => [
                'id' => $invitation->id, 'name' => $invitation->name, 'email' => $invitation->email,
                'permissions' => $invitation->permissions ?? [], 'status' => $invitation->expires_at->isPast() ? 'expired' : 'pending',
                'expires_at' => $invitation->expires_at,
            ]);

        return response()->json(['data' => $members, 'invitations' => $invitations, 'permission_groups' => CompanyMembership::permissionGroups()]);
    }

    public function store(Request $request, TenantContext $tenant, AuditLogger $audit)
    {
        $this->authorizeManagement($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'role' => ['sometimes', Rule::in(['manager', 'agent', 'operations', 'finance'])],
            'permissions' => ['sometimes', 'array', 'max:'.count(CompanyMembership::permissionKeys())],
            'permissions.*' => ['string', 'distinct', Rule::in(CompanyMembership::permissionKeys())],
        ]);
        $role = $data['role'] ?? 'agent';
        if (array_key_exists('permissions', $data)) $this->authorizePermissionChanges($request);
        $permissionChanges = array_key_exists('permissions', $data) ? ['permissions' => array_values(array_unique($data['permissions']))] : [];

        $data['email'] = Str::lower($data['email']);
        $user = User::whereRaw('LOWER(email) = ?', [$data['email']])->first();
        if ($user) {
            $membership = CompanyMembership::where('company_id', $tenant->id())->where('user_id', $user->id)->first();
            if ($membership && $membership->status === 'active') {
                throw ValidationException::withMessages(['email' => 'This person is already an active member.']);
            }

            $this->ensureTeamCapacity($tenant->id());

            if ($membership) {
                $old = $membership->only(['role', 'status', 'permissions', 'external_account_status']);
                $membership->update(['role' => $data['role'] ?? $membership->role, 'status' => 'active', 'external_account_status' => null, 'joined_at' => now()] + $permissionChanges);
                $event = 'team.member_reactivated';
            } else {
                $membership = CompanyMembership::create([
                    'company_id' => $tenant->id(), 'user_id' => $user->id, 'role' => $role,
                    'status' => 'active', 'joined_at' => now(),
                ] + $permissionChanges);
                $old = [];
                $event = 'team.member_added';
            }

            $audit->log($request, $event, $membership, $old, $membership->only(['role', 'status', 'permissions', 'external_account_status']));
            return response()->json(['member' => $this->serialize($membership->load('user:id,name,email'))], 201);
        }

        $plainToken = Str::random(64);
        $existingInvitation = CompanyInvitation::where('company_id', $tenant->id())
            ->where('email', $data['email'])->whereNull('accepted_at')->whereNull('revoked_at')->latest()->first();
        if ($existingInvitation) {
            $existingInvitation->update([
                'invited_by' => $request->user()->id, 'name' => $data['name'], 'role' => $role,
                'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDays(7),
            ] + $permissionChanges);
            $invitation = $existingInvitation->fresh();
            $event = 'team.invitation_resent';
        } else {
            $this->ensureTeamCapacity($tenant->id());
            $invitation = CompanyInvitation::create([
                'company_id' => $tenant->id(), 'invited_by' => $request->user()->id,
                'name' => $data['name'], 'email' => $data['email'], 'role' => $role,
                'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDays(7),
            ] + $permissionChanges);
            $event = 'team.invitation_created';
        }
        $audit->log($request, $event, $invitation, [], ['email' => $invitation->email, 'permissions' => $invitation->permissions ?? []]);
        $url = url('/accept-invite?token='.urlencode($plainToken));
        Mail::to($invitation->email)->send(new TeamInvitationMail($invitation->load(['company', 'inviter']), $url));

        return response()->json(['invitation' => [
            'id' => $invitation->id, 'name' => $invitation->name, 'email' => $invitation->email,
            'permissions' => $invitation->permissions ?? [], 'status' => 'pending', 'expires_at' => $invitation->expires_at,
        ]], 201);
    }

    public function revoke(Request $request, TenantContext $tenant, int $invitation, AuditLogger $audit)
    {
        $this->authorizeManagement($request);
        $invitation = CompanyInvitation::where('company_id', $tenant->id())->whereNull('accepted_at')->whereNull('revoked_at')->findOrFail($invitation);
        $invitation->update(['revoked_at' => now()]);
        $audit->log($request, 'team.invitation_revoked', $invitation, ['status' => 'pending'], ['status' => 'revoked']);
        return response()->noContent();
    }

    public function update(Request $request, TenantContext $tenant, int $membership, AuditLogger $audit)
    {
        $this->authorizeManagement($request);
        $target = CompanyMembership::where('company_id', $tenant->id())->with('user:id,name,email')->findOrFail($membership);
        $data = $request->validate([
            'role' => ['sometimes', Rule::in(['admin', 'manager', 'agent', 'operations', 'finance'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'permissions' => ['sometimes', 'array', 'max:'.count(CompanyMembership::permissionKeys())],
            'permissions.*' => ['string', 'distinct', Rule::in(CompanyMembership::permissionKeys())],
        ]);
        if (! $data) abort(422);
        if (array_key_exists('permissions', $data)) {
            $this->authorizePermissionChanges($request);
            abort_if(in_array($target->role, ['owner', 'admin'], true), 422, 'Full-access memberships are managed through their role.');
            $data['permissions'] = array_values(array_unique($data['permissions']));
        }
        if (array_key_exists('status', $data)) $data['external_account_status'] = null;

        if ($target->user_id === $request->user()->id && (($data['status'] ?? $target->status) !== 'active' || ($data['role'] ?? $target->role) !== $target->role)) {
            throw ValidationException::withMessages(['membership' => 'You cannot deactivate yourself or change your own role.']);
        }

        if ($target->role === 'owner' && (($data['role'] ?? 'owner') !== 'owner' || ($data['status'] ?? 'active') !== 'active')) {
            $otherOwners = CompanyMembership::where('company_id', $tenant->id())->where('role', 'owner')->where('status', 'active')->whereKeyNot($target->id)->count();
            if ($otherOwners === 0) {
                throw ValidationException::withMessages(['membership' => 'The company must keep at least one active owner.']);
            }
        }

        $old = $target->only(['role', 'status', 'permissions', 'external_account_status']);
        $target->update($data);
        $audit->log($request, 'team.member_updated', $target, $old, $target->fresh()->only(['role', 'status', 'permissions', 'external_account_status']));

        return response()->json($this->serialize($target->fresh()->load('user:id,name,email')));
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->attributes->get('membership')->can('manage_members'), 403);
    }

    private function authorizePermissionChanges(Request $request): void
    {
        abort_unless(in_array($request->attributes->get('membership')->role, ['owner', 'admin'], true), 403, 'Only owners and admins can grant or remove permissions.');
    }

    private function ensureTeamCapacity(int $companyId): void
    {
        $company = \App\Models\Company::findOrFail($companyId);
        $limit = app(SubscriptionService::class)->current($company)?->plan?->team_member_limit;
        if ($limit === null) return;

        $activeMembers = CompanyMembership::where('company_id', $companyId)->where('status', 'active')->count();
        $pendingInvitations = CompanyInvitation::where('company_id', $companyId)
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count();
        if ($activeMembers + $pendingInvitations >= $limit) {
            throw ValidationException::withMessages(['plan' => "Your current plan allows up to {$limit} team members. Upgrade your plan to add more."]);
        }
    }

    private function serialize(CompanyMembership $membership): array
    {
        return [
            'id' => $membership->id, 'user' => $membership->user, 'role' => $membership->role,
            'permissions' => $membership->effectivePermissions(),
            'full_access' => in_array($membership->role, ['owner', 'admin'], true),
            'status' => $membership->status, 'joined_at' => $membership->joined_at, 'created_at' => $membership->created_at,
        ];
    }
}
