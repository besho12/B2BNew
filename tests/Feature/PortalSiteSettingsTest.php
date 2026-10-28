<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalSiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_owner_can_save_global_brand_settings_and_the_portal_can_read_them(): void
    {
        $owner = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($owner)->postJson('/api/v1/platform/site-settings', [
            'site_name' => 'Northstar Supply',
            'tagline' => 'Wholesale, made simple',
            'welcome_heading' => 'Wholesale, made simple.',
            'welcome_message' => 'Shop products and manage your account in one place.',
            'header_announcement' => 'Orders placed before noon ship today.',
            'announcement_enabled' => true,
            'header_style' => 'dark',
            'primary_color' => '#173B3A',
            'secondary_color' => '#648779',
            'accent_color' => '#C8A66D',
            'surface_color' => '#F4F5F1',
            'footer_text' => 'For business customers.',
            'contact_email' => 'hello@example.test',
            'contact_phone' => '+20 2 0000 0000',
            'address' => 'Cairo, Egypt',
            'facebook_url' => 'https://facebook.com/northstar',
            'instagram_url' => 'https://instagram.com/northstar',
            'linkedin_url' => 'https://linkedin.com/company/northstar',
            'landing_content' => json_encode([
                'hero' => ['primary_label' => 'Enter account', 'secondary_label' => 'Browse catalogue'],
                'promo' => ['enabled' => true, 'video_url' => 'https://cdn.example.test/promo.mp4', 'poster_url' => null, 'eyebrow' => 'TRADE', 'heading' => 'Keep moving', 'body' => 'A business-first promo.', 'button_label' => 'Browse products', 'button_path' => '/products'],
                'catalog' => ['enabled' => true, 'eyebrow' => 'CATALOGUE', 'heading' => 'Browse the range', 'body' => 'Find products for your business.', 'tiles' => [
                    ['eyebrow' => '01 / CARE', 'title' => 'Cleaning'], ['eyebrow' => '02 / WORKSHOP', 'title' => 'Tools'], ['eyebrow' => '03 / RIDE', 'title' => 'Essentials'],
                ]],
                'benefits' => ['enabled' => true, 'eyebrow' => 'BENEFITS', 'heading' => 'Built for trade', 'cards' => [
                    ['title' => 'Discover', 'body' => 'Find products.'], ['title' => 'Order', 'body' => 'Order quickly.'], ['title' => 'Manage', 'body' => 'Manage accounts.'],
                ]],
                'cta' => ['enabled' => true, 'eyebrow' => 'READY', 'heading' => 'Sign in', 'body' => 'Continue to the portal.', 'button_label' => 'Sign in'],
            ], JSON_THROW_ON_ERROR),
        ])->assertOk()
            ->assertJsonPath('settings.site_name', 'Northstar Supply')
            ->assertJsonPath('settings.header_style', 'dark')
            ->assertJsonPath('settings.announcement_enabled', true)
            ->assertJsonPath('settings.landing_content.promo.heading', 'Keep moving');

        $this->getJson('/api/v1/site-settings')->assertOk()
            ->assertJsonPath('site_name', 'Northstar Supply')
            ->assertJsonPath('primary_color', '#173B3A')
            ->assertJsonPath('contact_email', 'hello@example.test')
            ->assertJsonPath('landing_content.catalog.heading', 'Browse the range');

        $this->assertDatabaseHas('platform_admin_actions', [
            'admin_user_id' => $owner->id,
            'action' => 'portal_site_settings_updated',
        ]);
    }

    public function test_company_users_cannot_read_or_change_portal_owner_settings(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);

        $this->actingAs($user)->getJson('/api/v1/platform/site-settings')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/platform/site-settings', [])->assertForbidden();
    }

    public function test_site_brand_colors_must_be_valid_hex_values(): void
    {
        $owner = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($owner)->postJson('/api/v1/platform/site-settings', [
            'site_name' => 'Portal', 'tagline' => 'Business portal', 'welcome_heading' => 'Welcome',
            'welcome_message' => 'Manage your business account.', 'announcement_enabled' => false,
            'header_style' => 'light', 'primary_color' => 'green', 'secondary_color' => '#648779',
            'accent_color' => '#C8A66D', 'surface_color' => '#F4F5F1',
        ])->assertUnprocessable()->assertJsonValidationErrors('primary_color');
    }

    public function test_landing_promo_only_accepts_secure_direct_video_files(): void
    {
        $owner = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($owner)->postJson('/api/v1/platform/site-settings', [
            'site_name' => 'Portal', 'tagline' => 'Business portal', 'welcome_heading' => 'Welcome',
            'welcome_message' => 'Manage your account.', 'announcement_enabled' => false, 'header_style' => 'light',
            'primary_color' => '#173B3A', 'secondary_color' => '#648779', 'accent_color' => '#C8A66D', 'surface_color' => '#F4F5F1',
            'landing_content' => [
                'hero' => ['primary_label' => 'Sign in', 'secondary_label' => 'Browse'],
                'promo' => ['enabled' => true, 'video_url' => 'http://example.test/promo.mp4', 'poster_url' => null, 'eyebrow' => 'PROMO', 'heading' => 'Welcome', 'body' => 'A promo', 'button_label' => 'Sign in', 'button_path' => '/login'],
                'catalog' => ['enabled' => true, 'eyebrow' => 'CATALOGUE', 'heading' => 'Catalogue', 'body' => 'Browse products.', 'tiles' => [
                    ['eyebrow' => '01 / CARE', 'title' => 'Cleaning'], ['eyebrow' => '02 / WORKSHOP', 'title' => 'Tools'], ['eyebrow' => '03 / RIDE', 'title' => 'Essentials'],
                ]],
                'benefits' => ['enabled' => true, 'eyebrow' => 'BENEFITS', 'heading' => 'Benefits', 'cards' => [
                    ['title' => 'One', 'body' => 'First.'], ['title' => 'Two', 'body' => 'Second.'], ['title' => 'Three', 'body' => 'Third.'],
                ]],
                'cta' => ['enabled' => true, 'eyebrow' => 'READY', 'heading' => 'Sign in', 'body' => 'Continue.', 'button_label' => 'Sign in'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('landing_content.promo.video_url');
    }

    public function test_portal_owner_logo_is_saved_privately_and_served_through_the_public_brand_route(): void
    {
        Storage::fake('private');
        $owner = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($owner)->post('/api/v1/platform/site-settings', [
            'site_name' => 'Portal', 'tagline' => 'Business portal', 'welcome_heading' => 'Welcome',
            'welcome_message' => 'Manage your account.', 'announcement_enabled' => '0', 'header_style' => 'light',
            'primary_color' => '#173B3A', 'secondary_color' => '#648779', 'accent_color' => '#C8A66D',
            'surface_color' => '#F4F5F1', 'logo' => UploadedFile::fake()->image('portal-logo.png'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('settings.logo_url', route('portal-site.logo'));

        $setting = \App\Models\PortalSiteSetting::query()->firstOrFail();
        Storage::disk('private')->assertExists($setting->logo_path);
        $this->get('/api/v1/site-settings/logo')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
