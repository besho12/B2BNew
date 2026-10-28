<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\B2BPortalService;
use App\Services\RegistrationCaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends Controller
{
    public function login(Request $request, B2BPortalService $portal, AuditLogger $audit)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 422);
        }

        $request->session()->regenerate();
        if (! $request->user()->hasVerifiedEmail()) {
            return response()->json([
                'verification_required' => true,
                'email' => $request->user()->email,
                'message' => 'Verify your email address before opening your workspace.',
            ], 403);
        }

        $user = $request->user();
        if (! $user->is_platform_admin && ! $user->memberships()->where('status', 'active')->exists()) {
            $pendingMembership = $user->memberships()
                ->where('company_id', $user->current_company_id)
                ->where('status', 'inactive')
                ->where('external_account_status', 'not_found')
                ->first();

            if (! $pendingMembership) {
                return $this->rejectSignIn($request, 'Your account is not linked to an active company yet. Contact the portal administrator.', 403);
            }

            if (! $portal->isConfigured()) {
                return $this->rejectSignIn($request, 'Customer account verification is not configured. Please try again later.', 503);
            }

            try {
                $hasCustomerAccount = $portal->accounts($user->email) !== [];
            } catch (RuntimeException $exception) {
                report($exception);
                return $this->rejectSignIn($request, 'Customer account verification is temporarily unavailable. Please try again later.', 503);
            }

            if (! $hasCustomerAccount) {
                return $this->rejectSignIn($request, 'This email is not linked to an active B2B customer account yet.', 403, ['customer_account_required' => true]);
            }

            $old = $pendingMembership->only(['status', 'external_account_status']);
            $pendingMembership->update([
                'status' => 'active',
                'external_account_status' => 'matched',
                'joined_at' => now(),
            ]);
            $audit->log($request, 'membership.customer_account_verified', $pendingMembership, $old, $pendingMembership->only(['status', 'external_account_status']));
        }

        return response()->json(['message' => 'Welcome back.']);
    }

    public function register(Request $request, RegistrationCaptchaService $captcha)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'captcha' => ['required', 'string', 'max:10'],
        ]);

        if (! $captcha->consume($request, $data['captcha'])) {
            throw ValidationException::withMessages(['captcha' => ['The CAPTCHA answer is incorrect or expired. Please refresh it and try again.']]);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->sendEmailVerificationNotification();
        return response()->json([
            'message' => 'Your user account has been created. Verify your email; company access and permissions will be assigned separately.',
            'email' => $user->email,
        ], 201);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Signed out.']);
    }

    private function rejectSignIn(Request $request, string $message, int $status, array $extra = [])
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => $message] + $extra, $status);
    }

}
