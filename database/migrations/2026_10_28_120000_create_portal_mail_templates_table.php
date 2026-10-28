<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 40)->unique();
            $table->string('subject', 190);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_mail_templates');
    }
};
