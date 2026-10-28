<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AuthContextController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $originalUser = $request->session()->get('impersonator_user_id')
            ? User::find($request->session()->get('impersonator_user_id'))
            : null;

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'is_platform_admin' => (bool) $user->is_platform_admin,
            'impersonation' => $request->session()->has('impersonator_user_id') ? [
                'original_user' => $originalUser ? ['id' => $originalUser->id, 'name' => $originalUser->name, 'email' => $originalUser->email] : null,
            ] : null,
        ]);
    }
}
