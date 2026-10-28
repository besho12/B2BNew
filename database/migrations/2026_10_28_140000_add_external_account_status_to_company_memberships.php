<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_memberships', function (Blueprint $table) {
            $table->string('external_account_status', 24)->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('company_memberships', function (Blueprint $table) {
            $table->dropColumn('external_account_status');
        });
    }
};
