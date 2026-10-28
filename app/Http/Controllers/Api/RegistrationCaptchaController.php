<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RegistrationCaptchaService;
use Illuminate\Http\Request;

class RegistrationCaptchaController extends Controller
{
    public function __invoke(Request $request, RegistrationCaptchaService $captcha)
    {
        return response($captcha->issue($request), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
