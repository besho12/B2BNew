<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 80)->default('B2B Portal');
            $table->string('tagline', 140)->default('Business customer portal');
            $table->string('welcome_heading', 160)->default('Every move brings you closer to home.');
            $table->string('welcome_message', 500)->default('A simpler way to stay connected with your business customers.');
            $table->string('header_announcement', 220)->nullable();
            $table->boolean('announcement_enabled')->default(false);
            $table->string('header_style', 20)->default('light');
            $table->char('primary_color', 7)->default('#173B3A');
            $table->char('secondary_color', 7)->default('#648779');
            $table->char('accent_color', 7)->default('#C8A66D');
            $table->char('surface_color', 7)->default('#F4F5F1');
            $table->string('footer_text', 500)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('logo_mime_type', 100)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_site_settings');
    }
};
