<?php

namespace App\Console\Commands;

use App\Models\EmailDomain;
use App\Models\StaffMailbox;
use Illuminate\Console\Command;

class RegisterStaffMailbox extends Command
{
    protected $signature = 'absterco:register-mailbox
                            {email : e.g. savindu@email.absterco.com}
                            {--password= : Mailbox SMTP/IMAP password (required for new mailboxes)}
                            {--display-name= : Display name for From header}
                            {--staff-user-id= : CRM staff user UUID}
                            {--domain=email.absterco.com : Email domain}
                            {--inactive : Deactivate instead of activate}';

    protected $description = 'Register or update a staff outreach mailbox on email.absterco.com';

    public function handle(): int
    {
        if (!config('app.key')) {
            $this->error('APP_KEY is missing — run: php artisan key:generate --force');
            return self::FAILURE;
        }

        $email = strtolower(trim($this->argument('email')));
        $domainName = strtolower(trim($this->option('domain')));

        $parts = explode('@', $email);
        if (count($parts) !== 2 || $parts[1] !== $domainName) {
            $this->error("Email must be an address on {$domainName}");
            return self::FAILURE;
        }

        $domain = EmailDomain::where('domain', $domainName)->first();
        if (!$domain) {
            $this->error("Domain {$domainName} not found — run AbstercoCrmSeeder first");
            return self::FAILURE;
        }

        $password = $this->option('password');
        $mailbox = StaffMailbox::where('email', $email)->first();
        $isNew = $mailbox === null;

        if ($isNew && ($password === null || $password === '')) {
            $this->error('--password is required when creating a new mailbox');
            return self::FAILURE;
        }

        $domainConfig = $domain->mail_config ?? [];
        $inbound = $domainConfig['inbound'] ?? [];

        if ($isNew) {
            $mailbox = StaffMailbox::create([
                'email_domain_id' => $domain->id,
                'email' => $email,
                'display_name' => $this->option('display-name') ?: $parts[0],
                'staff_user_id' => $this->option('staff-user-id'),
                'smtp_host' => $domainConfig['host'] ?? 'uniform.de.hostns.io',
                'smtp_port' => $domainConfig['port'] ?? 465,
                'smtp_encryption' => $domainConfig['encryption'] ?? 'ssl',
                'smtp_username' => $email,
                'smtp_password' => $password,
                'imap_host' => $inbound['host'] ?? $domainConfig['host'] ?? 'uniform.de.hostns.io',
                'imap_port' => $inbound['port'] ?? 993,
                'imap_encryption' => $inbound['encryption'] ?? 'ssl',
                'imap_username' => $email,
                'imap_password' => $password,
                'imap_folder' => $inbound['folder'] ?? 'INBOX',
                'active' => !$this->option('inactive'),
            ]);
            $this->info("Created mailbox: {$email}");
        } else {
            $updates = [
                'display_name' => $this->option('display-name') ?: $mailbox->display_name,
                'active' => !$this->option('inactive'),
            ];
            if ($this->option('staff-user-id')) {
                $updates['staff_user_id'] = $this->option('staff-user-id');
            }
            if ($password !== null && $password !== '') {
                $updates['smtp_password'] = $password;
                $updates['imap_password'] = $password;
            }
            $mailbox->update($updates);
            $this->info("Updated mailbox: {$email}");
        }

        $this->line('  domain       : ' . $domainName);
        $this->line('  display_name : ' . $mailbox->display_name);
        $this->line('  active       : ' . ($mailbox->active ? 'yes' : 'no'));
        if ($password) {
            $this->line('  password     : saved (encrypted)');
        }

        return self::SUCCESS;
    }
}
