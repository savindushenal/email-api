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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('email_domains')->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->onDelete('set null');
            
            $table->string('from_email');
            $table->string('to_email');
            $table->string('subject');
            $table->string('template_key')->nullable();
            
            $table->enum('status', ['sent', 'failed', 'queued'])->default('queued');
            $table->text('error_message')->nullable();
            
            $table->string('mailer_used'); // exim or ses
            $table->string('message_id')->nullable(); // For tracking
            
            $table->json('variables')->nullable(); // Data passed to template
            
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index('domain_id');
            $table->index('to_email');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
