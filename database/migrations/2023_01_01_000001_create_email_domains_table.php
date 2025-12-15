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
        Schema::create('email_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('from_email');
            $table->string('from_name');
            $table->enum('mailer', ['exim', 'ses'])->default('exim');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('api_key')->unique();
            
            // SES Configuration (optional, only for SES mailer)
            $table->string('ses_key')->nullable();
            $table->string('ses_secret')->nullable();
            $table->string('ses_region')->default('us-east-1')->nullable();
            
            // Rate limiting
            $table->integer('daily_limit')->default(1000);
            $table->integer('hourly_limit')->default(100);
            
            $table->timestamps();
            
            $table->index('domain');
            $table->index('api_key');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_domains');
    }
};
