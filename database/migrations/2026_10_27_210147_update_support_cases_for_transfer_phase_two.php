<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_cases', function (Blueprint $table) {
            if (Schema::hasColumn('support_cases', 'lead_id')) {
                $table->dropForeign(['lead_id']);
                $table->dropColumn('lead_id');
            }
            $table->string('system_reference', 120)->nullable();
            $table->string('support_contract', 120)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->string('contact_person', 160)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->text('feedback_comment')->nullable();
            $table->timestamp('feedback_submitted_at')->nullable();
            $table->foreignId('feedback_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('support_case_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_case_message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('storage_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->index(['company_id', 'support_case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_case_attachments');
        Schema::table('support_cases', function (Blueprint $table) {
            $table->dropForeign(['feedback_by']);
            $table->dropColumn([
                'system_reference', 'support_contract', 'serial_number',
                'contact_person', 'contact_email', 'contact_phone',
                'feedback_rating', 'feedback_comment', 'feedback_submitted_at', 'feedback_by',
            ]);
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};