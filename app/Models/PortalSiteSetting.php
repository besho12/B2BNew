<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalSiteSetting extends Model
{
    protected $fillable = [
        'site_name', 'tagline', 'welcome_heading', 'welcome_message', 'header_announcement', 'announcement_enabled', 'header_style',
        'primary_color', 'secondary_color', 'accent_color', 'surface_color', 'footer_text',
        'contact_email', 'contact_phone', 'address', 'facebook_url', 'instagram_url', 'linkedin_url',
        'logo_path', 'logo_mime_type', 'landing_content', 'updated_by',
    ];

    protected $casts = ['announcement_enabled' => 'boolean', 'landing_content' => 'array'];
}
