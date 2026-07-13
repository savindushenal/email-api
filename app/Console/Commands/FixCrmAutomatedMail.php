<?php

namespace App\Console\Commands;

use App\Models\EmailDomain;
use App\Services\DomainMailConfigService;
use Illuminate\Console\Command;

class FixCrmAutomatedMail extends Command
{
    protected $signature = 'absterco:fix-crm-mail
                            {--password= : SMTP password for system@crm.absterco.com (defaults to CRM_DOMAIN_SMTP_PASSWORD env)}';

    protected $description = 'Set crm.absterco.com automated mail to system@ with SMTP credentials in the database';

    public function handle(DomainMailConfigService $mailConfigService): int
    {
        if (!config('app.key')) {
            $this->error('APP_KEY is missing — run: php artisan key:generate --force');
            return self::FAILURE;
        }

        $password = $this->option('password') ?: env('CRM_DOMAIN_SMTP_PASSWORD') ?: env('EMAIL_DOMAIN_SMTP_PASSWORD');
        if ($password === null || $password === '') {
            $this->error('SMTP password required — set CRM_DOMAIN_SMTP_PASSWORD in .env or pass --password=');
            return self::FAILURE;
        }

        $domain = EmailDomain::where('domain', 'crm.absterco.com')->first();
        if (!$domain) {
            $this->error('Domain crm.absterco.com not found — run AbstercoCrmSeeder first');
            return self::FAILURE;
        }

        $domain->from_email = 'system@crm.absterco.com';
        $domain->from_name = 'Absterco CRM';
        $domain->mail_config = $mailConfigService->prepareForStorage(
            $domain->mail_config,
            [
                'transport' => 'smtp',
                'host' => 'uniform.de.hostns.io',
                'port' => 465,
                'encryption' => 'ssl',
                'username' => 'system@crm.absterco.com',
                'password' => $password,
                'inbound' => ['enabled' => false],
            ]
        );
        $domain->save();

        $this->info('Updated crm.absterco.com');
        $this->line('  from_email : ' . $domain->from_email);
        $this->line('  smtp user  : system@crm.absterco.com');
        $this->line('  password   : saved (encrypted)');

        return self::SUCCESS;
    }
}
