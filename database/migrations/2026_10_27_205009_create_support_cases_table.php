<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_cases')) {
            Schema::create('support_cases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('subject', 190);
                $table->text('description');
                $table->string('category', 40)->default('general');
                $table->string('priority', 20)->default('normal');
                $table->string('status', 20)->default('open');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'status', 'updated_at']);
                $table->index(['company_id', 'assigned_to', 'status']);
            });
        }

        if (! Schema::hasTable('support_case_messages')) {
            Schema::create('support_case_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('support_case_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->text('body');
                $table->boolean('is_internal')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('support_case_messages', 'scm_company_case_created_idx')) {
            Schema::table('support_case_messages', function (Blueprint $table) {
                $table->index(['company_id', 'support_case_id', 'created_at'], 'scm_company_case_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_case_messages');
        Schema::dropIfExists('support_cases');
    }
};
