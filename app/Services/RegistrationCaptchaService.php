<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegistrationCaptchaService
{
    private const SESSION_KEY = 'registration_captcha_hash';
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function issue(Request $request): string
    {
        $answer = collect(range(1, 6))
            ->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
            ->implode('');

        $request->session()->put(self::SESSION_KEY, [
            'digest' => $this->digest($answer),
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);

        return $this->renderImage($answer);
    }

    public function consume(Request $request, string $answer): bool
    {
        $expected = $request->session()->pull(self::SESSION_KEY);
        if (! is_array($expected)
            || ! is_string($expected['digest'] ?? null)
            || (int) ($expected['expires_at'] ?? 0) < now()->timestamp) {
            return false;
        }

        return hash_equals($expected['digest'], $this->digest(Str::upper(trim($answer))));
    }

    private function digest(string $answer): string
    {
        return hash_hmac('sha256', $answer, (string) config('app.key'));
    }

    private function renderImage(string $answer): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('The GD extension is required to render the registration CAPTCHA.');
        }

        $width = 236;
        $height = 60;
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 250, 250, 248);
        imagefill($image, 0, 0, $background);

        for ($i = 0; $i < 11; $i++) {
            $noise = imagecolorallocate($image, random_int(175, 220), random_int(175, 220), random_int(175, 220));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $noise);
        }

        for ($i = 0; $i < 340; $i++) {
            $noise = imagecolorallocate($image, random_int(195, 232), random_int(195, 232), random_int(195, 232));
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), $noise);
        }

        for ($i = 0; $i < strlen($answer); $i++) {
            $ink = imagecolorallocate($image, random_int(45, 120), random_int(45, 120), random_int(45, 120));
            imagestring($image, 5, 17 + ($i * 35) + random_int(-2, 2), random_int(19, 31), $answer[$i], $ink);
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png === false ? '' : $png;
    }
}
