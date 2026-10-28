<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdminAction;
use App\Models\PortalSiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PortalSiteSettingsController extends Controller
{
    private const DEFAULTS = [
        'site_name' => 'B2B Portal',
        'tagline' => 'Business customer portal',
        'welcome_heading' => 'Every move brings you closer to home.',
        'welcome_message' => 'A simpler way to stay connected with your business customers.',
        'header_announcement' => null,
        'announcement_enabled' => false,
        'header_style' => 'light',
        'primary_color' => '#173B3A',
        'secondary_color' => '#648779',
        'accent_color' => '#C8A66D',
        'surface_color' => '#F4F5F1',
        'footer_text' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'address' => null,
        'facebook_url' => null,
        'instagram_url' => null,
        'linkedin_url' => null,
        'landing_content' => [
            'hero' => ['primary_label' => 'Enter your account', 'secondary_label' => 'Explore the range'],
            'promo' => [
                'enabled' => true,
                'video_url' => 'https://cdn.shopify.com/videos/c/o/v/23691f5531374e26b516e8b037792756.mp4',
                'poster_url' => null,
                'eyebrow' => 'BUILT FOR EVERY RIDE',
                'heading' => 'The next ride starts here.',
                'body' => 'Discover the products, support and tools that keep your business moving.',
                'button_label' => 'Explore the catalogue',
                'button_path' => '/products',
            ],
            'catalog' => [
                'enabled' => true,
                'eyebrow' => 'YOUR BUSINESS CATALOGUE',
                'heading' => 'Everything your customers need, in one place.',
                'body' => 'Browse the full product range, see account-specific availability and find the right items faster.',
                'tiles' => [
                    ['eyebrow' => '01 / CARE', 'title' => 'Cleaning & care'],
                    ['eyebrow' => '02 / WORKSHOP', 'title' => 'Tools & workshop'],
                    ['eyebrow' => '03 / RIDE', 'title' => 'Everyday essentials'],
                ],
            ],
            'benefits' => [
                'enabled' => true,
                'eyebrow' => 'A BETTER WAY TO ORDER',
                'heading' => 'Built around the way your business works.',
                'cards' => [
                    ['title' => 'Find the right products', 'body' => 'Search and filter the catalogue, then save favourites for the next order.'],
                    ['title' => 'Order at your pace', 'body' => 'Build a basket line by line or enter multiple product codes at once.'],
                    ['title' => 'Stay in control', 'body' => 'Switch customer accounts and review account details, addresses and order history.'],
                ],
            ],
            'cta' => [
                'enabled' => true,
                'eyebrow' => 'READY WHEN YOU ARE',
                'heading' => 'A simpler way to keep business moving.',
                'body' => 'Sign in to your account to browse products and prepare your next order.',
                'button_label' => 'Sign in to the portal',
            ],
        ],
    ];

    public function show()
    {
        return response()->json($this->serialize($this->setting()));
    }

    public function logo()
    {
        $setting = $this->writableSetting();
        abort_unless($setting->logo_path && Storage::disk('private')->exists($setting->logo_path), 404);

        return Storage::disk('private')->response($setting->logo_path, 'portal-logo.'.pathinfo($setting->logo_path, PATHINFO_EXTENSION), [
            'Content-Type' => $setting->logo_mime_type ?: 'image/png',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->is_platform_admin, 403, 'Portal owner access is required.');
        if (! $request->exists('landing_content')) {
            $request->merge(['landing_content' => $this->serialize($this->setting())['landing_content']]);
        } elseif (is_string($request->input('landing_content'))) {
            try {
                $landingContent = json_decode($request->input('landing_content'), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages(['landing_content' => ['The landing page settings are invalid.']]);
            }
            $request->merge(['landing_content' => $landingContent]);
        }

        $secureVideoUrl = static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') return;
            $parts = parse_url((string) $value);
            if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || ! in_array(strtolower(pathinfo((string) ($parts['path'] ?? ''), PATHINFO_EXTENSION)), ['mp4', 'webm'], true)) {
                $fail('Use an HTTPS URL to a direct MP4 or WebM video file.');
            }
        };
        $secureMediaUrl = static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') return;
            $parts = parse_url((string) $value);
            if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
                $fail('Use an HTTPS media URL.');
            }
        };
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'tagline' => ['required', 'string', 'max:140'],
            'welcome_heading' => ['required', 'string', 'max:160'],
            'welcome_message' => ['required', 'string', 'max:500'],
            'header_announcement' => [Rule::requiredIf($request->boolean('announcement_enabled')), 'nullable', 'string', 'max:220'],
            'announcement_enabled' => ['required', 'boolean'],
            'header_style' => ['required', Rule::in(['light', 'dark', 'brand'])],
            'primary_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'secondary_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'accent_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'surface_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'landing_content' => ['required', 'array:hero,promo,catalog,benefits,cta'],
            'landing_content.hero' => ['required', 'array:primary_label,secondary_label'],
            'landing_content.hero.primary_label' => ['required', 'string', 'max:80'],
            'landing_content.hero.secondary_label' => ['required', 'string', 'max:80'],
            'landing_content.promo' => ['required', 'array:enabled,video_url,poster_url,eyebrow,heading,body,button_label,button_path'],
            'landing_content.promo.enabled' => ['required', 'boolean'],
            'landing_content.promo.video_url' => ['required_if:landing_content.promo.enabled,1', 'nullable', 'url', 'max:2048', $secureVideoUrl],
            'landing_content.promo.poster_url' => ['nullable', 'url', 'max:2048', $secureMediaUrl],
            'landing_content.promo.eyebrow' => ['required', 'string', 'max:80'],
            'landing_content.promo.heading' => ['required', 'string', 'max:160'],
            'landing_content.promo.body' => ['required', 'string', 'max:400'],
            'landing_content.promo.button_label' => ['required', 'string', 'max:80'],
            'landing_content.promo.button_path' => ['required', Rule::in(['/products', '/register', '/login'])],
            'landing_content.catalog' => ['required', 'array:enabled,eyebrow,heading,body,tiles'],
            'landing_content.catalog.enabled' => ['required', 'boolean'],
            'landing_content.catalog.eyebrow' => ['required', 'string', 'max:80'],
            'landing_content.catalog.heading' => ['required', 'string', 'max:160'],
            'landing_content.catalog.body' => ['required', 'string', 'max:500'],
            'landing_content.catalog.tiles' => ['required', 'array', 'min:3', 'max:3'],
            'landing_content.catalog.tiles.*' => ['required', 'array:eyebrow,title'],
            'landing_content.catalog.tiles.*.eyebrow' => ['required', 'string', 'max:50'],
            'landing_content.catalog.tiles.*.title' => ['required', 'string', 'max:100'],
            'landing_content.benefits' => ['required', 'array:enabled,eyebrow,heading,cards'],
            'landing_content.benefits.enabled' => ['required', 'boolean'],
            'landing_content.benefits.eyebrow' => ['required', 'string', 'max:80'],
            'landing_content.benefits.heading' => ['required', 'string', 'max:160'],
            'landing_content.benefits.cards' => ['required', 'array', 'min:3', 'max:3'],
            'landing_content.benefits.cards.*' => ['required', 'array:title,body'],
            'landing_content.benefits.cards.*.title' => ['required', 'string', 'max:100'],
            'landing_content.benefits.cards.*.body' => ['required', 'string', 'max:300'],
            'landing_content.cta' => ['required', 'array:enabled,eyebrow,heading,body,button_label'],
            'landing_content.cta.enabled' => ['required', 'boolean'],
            'landing_content.cta.eyebrow' => ['required', 'string', 'max:80'],
            'landing_content.cta.heading' => ['required', 'string', 'max:160'],
            'landing_content.cta.body' => ['required', 'string', 'max:400'],
            'landing_content.cta.button_label' => ['required', 'string', 'max:80'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $upload = $data['logo'] ?? null;
        unset($data['logo']);
        $newLogoPath = null;
        if ($upload) {
            $extension = strtolower($upload->extension());
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                throw ValidationException::withMessages(['logo' => 'Choose a JPEG, PNG, or WebP logo image.']);
            }
            $newLogoPath = $upload->storeAs('portal/branding', Str::uuid().'.'.$extension, 'private');
        }

        $setting = $this->writableSetting();
        $oldLogoPath = $setting->logo_path;
        try {
            DB::transaction(function () use ($request, $setting, $data, $upload, $newLogoPath) {
                $setting->fill($data);
                $setting->updated_by = $request->user()->id;
                if ($upload && $newLogoPath) {
                    $setting->logo_path = $newLogoPath;
                    $setting->logo_mime_type = $upload->getMimeType();
                }
                $setting->save();

                PlatformAdminAction::create([
                    'admin_user_id' => $request->user()->id,
                    'action' => 'portal_site_settings_updated',
                    'reason' => 'Updated portal master settings',
                    'details' => ['fields' => array_values(array_keys($data)), 'logo_changed' => (bool) $upload],
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'created_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($newLogoPath) Storage::disk('private')->delete($newLogoPath);
            throw $exception;
        }

        if ($newLogoPath && $oldLogoPath && $oldLogoPath !== $newLogoPath) {
            Storage::disk('private')->delete($oldLogoPath);
        }

        return response()->json(['message' => 'Portal settings saved.', 'settings' => $this->serialize($setting->fresh())]);
    }

    public function removeLogo(Request $request)
    {
        abort_unless($request->user()?->is_platform_admin, 403, 'Portal owner access is required.');
        $setting = $this->setting();
        $oldLogoPath = $setting->logo_path;
        abort_unless($oldLogoPath, 404);

        DB::transaction(function () use ($request, $setting) {
            $setting->update(['logo_path' => null, 'logo_mime_type' => null, 'updated_by' => $request->user()->id]);
            PlatformAdminAction::create([
                'admin_user_id' => $request->user()->id,
                'action' => 'portal_site_logo_removed',
                'reason' => 'Removed portal brand logo',
                'details' => [],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        Storage::disk('private')->delete($oldLogoPath);

        return response()->json(['message' => 'Portal logo removed.', 'settings' => $this->serialize($setting->fresh())]);
    }

    private function setting(): PortalSiteSetting
    {
        return PortalSiteSetting::query()->find(1) ?? new PortalSiteSetting(self::DEFAULTS);
    }

    private function writableSetting(): PortalSiteSetting
    {
        return PortalSiteSetting::query()->firstOrNew(['id' => 1], self::DEFAULTS);
    }

    private function serialize(PortalSiteSetting $setting): array
    {
        $data = array_merge(self::DEFAULTS, $setting->only(array_keys(self::DEFAULTS)));
        $data['announcement_enabled'] = (bool) $data['announcement_enabled'];
        $data['landing_content'] = array_replace_recursive(self::DEFAULTS['landing_content'], $data['landing_content'] ?? []);
        $data['logo_url'] = $setting->logo_path ? route('portal-site.logo') : null;
        $data['updated_at'] = $setting->exists ? $setting->updated_at?->toIso8601String() : null;

        return $data;
    }
}
