<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_domains', function (Blueprint $table) {
            // Add mail_config JSON column to store SMTP/transport configurations per domain
            $table->json('mail_config')->nullable()->after('mailer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_domains', function (Blueprint $table) {
            $table->dropColumn('mail_config');
        });
    }
};
