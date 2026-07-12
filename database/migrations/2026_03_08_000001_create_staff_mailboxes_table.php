<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_domain_id')->constrained('email_domains')->cascadeOnDelete();
            $table->string('email')->unique();
            $table->string('display_name')->nullable();
            $table->string('staff_user_id', 36)->nullable()->index();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->default(465);
            $table->string('smtp_encryption', 10)->default('ssl');
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 10)->default('ssl');
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_folder', 100)->default('INBOX');
            $table->boolean('active')->default(true);
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_mailbox_processed_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_mailbox_id')->constrained('staff_mailboxes')->cascadeOnDelete();
            $table->string('message_uid', 255);
            $table->string('message_id', 255)->nullable();
            $table->timestamp('processed_at')->useCurrent();
            $table->unique(['staff_mailbox_id', 'message_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_mailbox_processed_messages');
        Schema::dropIfExists('staff_mailboxes');
    }
};
