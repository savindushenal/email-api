<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailDomain;
use App\Models\EmailTemplate;
use App\Services\DomainMailConfigService;
use Illuminate\Support\Str;

class AbstercoCrmSeeder extends Seeder
{
    /**
     * Raw API key for crm.absterco.com
     * Store this in CRM .env as EMAIL_API_KEY
     */
    const RAW_API_KEY = 'eak_AbstercoCRM2026xK9mLpQvTzWnRsYbJcFdHeGiUo';

    public function run(): void
    {
        $this->command->info('================================================');
        $this->command->info('  Absterco CRM — Email API Domain Setup');
        $this->command->info('================================================');
        $this->command->newLine();

        // -------------------------------------------------------
        // 1. Register domain
        // -------------------------------------------------------
        $existing = EmailDomain::where('domain', 'crm.absterco.com')->first();
        if ($existing) {
            $this->command->warn('Domain crm.absterco.com already exists — updating config...');
            $domain = $existing;
        } else {
            $domain = new EmailDomain();
        }

        $domain->domain      = 'crm.absterco.com';
        $domain->api_key     = hash('sha256', self::RAW_API_KEY);
        $domain->from_email  = 'system@crm.absterco.com';
        $domain->from_name   = 'Absterco CRM';
        $domain->mailer      = 'exim';   // enum: exim|ses — actual transport set in mail_config
        $domain->status      = 'active';
        $domain->daily_limit  = 2000;
        $domain->hourly_limit = 200;

        // Per-domain SMTP + inbound IMAP (deal reply polling when inbound.enabled)
        $mailConfigService = app(DomainMailConfigService::class);
        $domain->mail_config = $mailConfigService->prepareForStorage(null, [
            'transport'  => 'smtp',
            'host'       => 'uniform.de.hostns.io',
            'port'       => 465,
            'encryption' => 'ssl',
            'username'   => 'system@crm.absterco.com',
            'password'   => 'system@2026',
            'inbound'    => [
                'enabled'    => true,
                'host'       => 'uniform.de.hostns.io',
                'port'       => 993,
                'encryption' => 'ssl',
                'folder'     => 'INBOX',
                // username/password fall back to SMTP credentials above
            ],
        ]);

        $domain->save();

        $this->command->info("✅ Domain registered: {$domain->domain}");
        $this->command->info("   From email : {$domain->from_email}");
        $this->command->info("   SMTP host  : uniform.de.hostns.io:465 (SSL)");
        $this->command->info("   Inbound IMAP: enabled (polls same mailbox for deal replies)");
        $this->command->info("   API Key    : " . self::RAW_API_KEY);
        $this->command->newLine();

        // -------------------------------------------------------
        // 2. Create / update all CRM templates
        // -------------------------------------------------------
        foreach ($this->templates($domain->id) as $tpl) {
            EmailTemplate::updateOrCreate(
                ['domain_id' => $domain->id, 'template_key' => $tpl['template_key']],
                $tpl
            );
            $this->command->info("   Template created/updated: {$tpl['template_key']}");
        }

        $this->command->newLine();
        $this->command->info('================================================');
        $this->command->info('  Setup complete!  Copy values below to CRM .env');
        $this->command->info('================================================');
        $this->command->newLine();
        $this->command->line('EMAIL_API_BASE_URL=http://localhost:8001');
        $this->command->line('EMAIL_API_KEY=' . self::RAW_API_KEY);
        $this->command->line('EMAIL_API_DOMAIN=crm.absterco.com');
        $this->command->newLine();
    }

    // ------------------------------------------------------------------
    // Template definitions
    // ------------------------------------------------------------------
    private function templates(int $domainId): array
    {
        return [

            // ── 1. ticket-created ───────────────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'ticket-created',
                'category'     => 'notification',
                'description'  => 'Sent to client when a support/development ticket is opened',
                'subject'      => 'Ticket #{{ $ticket_number }} opened — {{ $ticket_title }}',
                'variables'    => [
                    ['name' => 'client_name',     'type' => 'string', 'description' => 'Recipient full name',            'required' => true],
                    ['name' => 'ticket_number',   'type' => 'string', 'description' => 'Ticket ID/reference number',     'required' => true],
                    ['name' => 'ticket_title',    'type' => 'string', 'description' => 'Ticket title',                   'required' => true],
                    ['name' => 'ticket_type',     'type' => 'string', 'description' => 'e.g. SUPPORT, BUG_REPORT',       'required' => true],
                    ['name' => 'ticket_priority', 'type' => 'string', 'description' => 'LOW / MEDIUM / HIGH / CRITICAL', 'required' => true],
                    ['name' => 'ticket_url',      'type' => 'url',    'description' => 'Link to ticket in client portal', 'required' => true],
                    ['name' => 'org_name',         'type' => 'string', 'description' => 'Organization name',              'required' => true],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700;letter-spacing:-.5px}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.body{padding:32px 40px}.body h2{margin:0 0 8px;color:#18181b;font-size:18px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}.badge-open{background:#dbeafe;color:#1d4ed8}.badge-high{background:#fee2e2;color:#b91c1c}.badge-critical{background:#fce7f3;color:#be185d}.badge-medium{background:#fef9c3;color:#854d0e}.badge-low{background:#dcfce7;color:#166534}.meta-table{width:100%;border-collapse:collapse;margin:20px 0}.meta-table td{padding:10px 0;border-bottom:1px solid #f4f4f5;font-size:14px;color:#52525b}.meta-table td:first-child{font-weight:600;color:#18181b;width:140px}.btn{display:inline-block;background:#84cc16;color:#18181b;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px;margin-top:8px}.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>Client Portal Notification</p></div><div class='body'><h2>Your ticket has been received</h2><p>Hi {{ \$client_name }}, we've opened a new ticket for <strong>{{ \$org_name }}</strong>. Our team will review it shortly.</p><table class='meta-table'><tr><td>Ticket</td><td><strong>#{{ \$ticket_number }}</strong></td></tr><tr><td>Title</td><td>{{ \$ticket_title }}</td></tr><tr><td>Type</td><td>{{ \$ticket_type }}</td></tr><tr><td>Priority</td><td><span class='badge badge-{{ strtolower(\$ticket_priority) }}'>{{ \$ticket_priority }}</span></td></tr><tr><td>Status</td><td><span class='badge badge-open'>OPEN</span></td></tr></table><a href='{{ \$ticket_url }}' class='btn'>View Ticket</a></div><div class='footer'><p>Absterco CRM &bull; crm.absterco.com &bull; You're receiving this because you have an active client account.</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 2. ticket-status-changed ────────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'ticket-status-changed',
                'category'     => 'notification',
                'description'  => 'Sent to client when staff changes ticket status',
                'subject'      => 'Ticket #{{ $ticket_number }} is now {{ $new_status }}',
                'variables'    => [
                    ['name' => 'client_name',   'type' => 'string', 'description' => 'Recipient full name',           'required' => true],
                    ['name' => 'ticket_number', 'type' => 'string', 'description' => 'Ticket reference number',       'required' => true],
                    ['name' => 'ticket_title',  'type' => 'string', 'description' => 'Ticket title',                  'required' => true],
                    ['name' => 'old_status',    'type' => 'string', 'description' => 'Previous status',               'required' => true],
                    ['name' => 'new_status',    'type' => 'string', 'description' => 'New status',                    'required' => true],
                    ['name' => 'progress',      'type' => 'number', 'description' => 'Completion % (0–100)',          'required' => true],
                    ['name' => 'staff_name',    'type' => 'string', 'description' => 'Staff member who updated it',   'required' => true],
                    ['name' => 'ticket_url',    'type' => 'url',    'description' => 'Link to ticket in client portal','required' => true],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.body{padding:32px 40px}.body h2{margin:0 0 8px;color:#18181b;font-size:18px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.progress-bar-bg{background:#f4f4f5;border-radius:999px;height:10px;margin:16px 0}.progress-bar-fill{background:#84cc16;border-radius:999px;height:10px}.meta-table{width:100%;border-collapse:collapse;margin:16px 0}.meta-table td{padding:10px 0;border-bottom:1px solid #f4f4f5;font-size:14px;color:#52525b}.meta-table td:first-child{font-weight:600;color:#18181b;width:140px}.btn{display:inline-block;background:#84cc16;color:#18181b;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px;margin-top:8px}.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>Ticket Status Update</p></div><div class='body'><h2>Status update on your ticket</h2><p>Hi {{ \$client_name }}, <strong>{{ \$staff_name }}</strong> has updated ticket <strong>#{{ \$ticket_number }}</strong>.</p><table class='meta-table'><tr><td>Ticket</td><td>#{{ \$ticket_number }} &mdash; {{ \$ticket_title }}</td></tr><tr><td>Previous Status</td><td>{{ \$old_status }}</td></tr><tr><td>New Status</td><td><strong>{{ \$new_status }}</strong></td></tr></table><p style='margin:8px 0 4px;font-size:13px;color:#71717a'>Overall Progress &mdash; {{ \$progress }}%</p><div class='progress-bar-bg'><div class='progress-bar-fill' style='width:{{ \$progress }}%'></div></div><a href='{{ \$ticket_url }}' class='btn'>View Full Ticket</a></div><div class='footer'><p>Absterco CRM &bull; crm.absterco.com</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 3. ticket-comment-client ────────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'ticket-comment-client',
                'category'     => 'notification',
                'description'  => 'Sent to client when staff adds a public comment on their ticket',
                'subject'      => 'New reply on ticket #{{ $ticket_number }} — {{ $ticket_title }}',
                'variables'    => [
                    ['name' => 'client_name',   'type' => 'string', 'description' => 'Client recipient name',           'required' => true],
                    ['name' => 'ticket_number', 'type' => 'string', 'description' => 'Ticket reference',                'required' => true],
                    ['name' => 'ticket_title',  'type' => 'string', 'description' => 'Ticket title',                    'required' => true],
                    ['name' => 'staff_name',    'type' => 'string', 'description' => 'Staff who commented',             'required' => true],
                    ['name' => 'comment_body',  'type' => 'string', 'description' => 'Plain-text comment content',      'required' => true],
                    ['name' => 'ticket_url',    'type' => 'url',    'description' => 'Link to ticket in client portal', 'required' => true],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.body{padding:32px 40px}.body h2{margin:0 0 8px;color:#18181b;font-size:18px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.comment-box{background:#f9fafb;border-left:3px solid #84cc16;border-radius:0 8px 8px 0;padding:16px 20px;margin:16px 0}.comment-box .author{font-size:13px;font-weight:600;color:#18181b;margin:0 0 6px}.comment-box .content{font-size:14px;color:#52525b;line-height:1.6;margin:0;white-space:pre-wrap}.btn{display:inline-block;background:#84cc16;color:#18181b;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px;margin-top:8px}.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>New Comment on Your Ticket</p></div><div class='body'><h2>{{ \$staff_name }} replied on #{{ \$ticket_number }}</h2><p>Hi {{ \$client_name }}, your ticket <strong>{{ \$ticket_title }}</strong> has a new reply from our team.</p><div class='comment-box'><p class='author'>{{ \$staff_name }} &mdash; Absterco Team</p><p class='content'>{{ \$comment_body }}</p></div><a href='{{ \$ticket_url }}' class='btn'>Reply or View Ticket</a></div><div class='footer'><p>Absterco CRM &bull; crm.absterco.com</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 4. ticket-comment-staff ─────────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'ticket-comment-staff',
                'category'     => 'notification',
                'description'  => 'Sent to assigned staff when client adds a comment on a ticket',
                'subject'      => '[Client Reply] Ticket #{{ $ticket_number }} — {{ $ticket_title }}',
                'variables'    => [
                    ['name' => 'staff_name',    'type' => 'string', 'description' => 'Staff recipient name',            'required' => true],
                    ['name' => 'client_name',   'type' => 'string', 'description' => 'Client who commented',            'required' => true],
                    ['name' => 'org_name',      'type' => 'string', 'description' => "Client's organization",           'required' => true],
                    ['name' => 'ticket_number', 'type' => 'string', 'description' => 'Ticket reference',                'required' => true],
                    ['name' => 'ticket_title',  'type' => 'string', 'description' => 'Ticket title',                    'required' => true],
                    ['name' => 'comment_body',  'type' => 'string', 'description' => 'Plain-text comment',              'required' => true],
                    ['name' => 'ticket_url',    'type' => 'url',    'description' => 'Link to ticket in staff portal',  'required' => true],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.alert-bar{background:#fef9c3;border-bottom:1px solid #fde047;padding:12px 40px;font-size:13px;color:#854d0e;font-weight:600}.body{padding:32px 40px}.body h2{margin:0 0 8px;color:#18181b;font-size:18px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.comment-box{background:#f9fafb;border-left:3px solid #f59e0b;border-radius:0 8px 8px 0;padding:16px 20px;margin:16px 0}.comment-box .author{font-size:13px;font-weight:600;color:#18181b;margin:0 0 6px}.comment-box .content{font-size:14px;color:#52525b;line-height:1.6;margin:0;white-space:pre-wrap}.btn{display:inline-block;background:#84cc16;color:#18181b;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px;margin-top:8px}.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>Staff Portal — Action Required</p></div><div class='alert-bar'>Client response received — ticket requires your attention</div><div class='body'><h2>{{ \$client_name }} replied on #{{ \$ticket_number }}</h2><p>Hi {{ \$staff_name }}, <strong>{{ \$client_name }}</strong> from <strong>{{ \$org_name }}</strong> has added a comment on ticket <strong>{{ \$ticket_title }}</strong>.</p><div class='comment-box'><p class='author'>{{ \$client_name }} ({{ \$org_name }})</p><p class='content'>{{ \$comment_body }}</p></div><a href='{{ \$ticket_url }}' class='btn'>Open in Staff Portal</a></div><div class='footer'><p>Absterco CRM Staff Portal &bull; crm.absterco.com/staff</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 5. ticket-milestone-completed ───────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'ticket-milestone-completed',
                'category'     => 'notification',
                'description'  => 'Sent to client when a milestone is checked off on their ticket',
                'subject'      => 'Milestone completed on ticket #{{ $ticket_number }}',
                'variables'    => [
                    ['name' => 'client_name',      'type' => 'string', 'description' => 'Recipient name',               'required' => true],
                    ['name' => 'ticket_number',    'type' => 'string', 'description' => 'Ticket reference',              'required' => true],
                    ['name' => 'ticket_title',     'type' => 'string', 'description' => 'Ticket title',                  'required' => true],
                    ['name' => 'milestone_title',  'type' => 'string', 'description' => 'Milestone that was completed',  'required' => true],
                    ['name' => 'milestones_done',  'type' => 'number', 'description' => 'Count of completed milestones', 'required' => true],
                    ['name' => 'milestones_total', 'type' => 'number', 'description' => 'Total milestones on ticket',    'required' => true],
                    ['name' => 'progress',         'type' => 'number', 'description' => 'Overall progress % (0–100)',    'required' => true],
                    ['name' => 'ticket_url',       'type' => 'url',    'description' => 'Link to ticket in portal',      'required' => true],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.body{padding:32px 40px}.milestone-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:20px 24px;margin:20px 0;display:flex;align-items:flex-start}.checkmark{font-size:24px;margin-right:16px;flex-shrink:0}.milestone-text .label{font-size:12px;font-weight:600;color:#16a34a;text-transform:uppercase;letter-spacing:.5px;margin:0 0 4px}.milestone-text .title{font-size:15px;font-weight:600;color:#18181b;margin:0}.body h2{margin:0 0 8px;color:#18181b;font-size:18px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.progress-label{font-size:13px;color:#71717a;margin:8px 0 4px}.progress-bar-bg{background:#f4f4f5;border-radius:999px;height:10px}.progress-bar-fill{background:#84cc16;border-radius:999px;height:10px}.btn{display:inline-block;background:#84cc16;color:#18181b;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px;margin-top:20px}.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>Project Milestone Update</p></div><div class='body'><h2>Milestone completed on #{{ \$ticket_number }}</h2><p>Hi {{ \$client_name }}, great news — a milestone on <strong>{{ \$ticket_title }}</strong> has been marked complete.</p><div class='milestone-box'><div class='checkmark'>&#10003;</div><div class='milestone-text'><p class='label'>Completed Milestone</p><p class='title'>{{ \$milestone_title }}</p></div></div><p>{{ \$milestones_done }} of {{ \$milestones_total }} milestones complete</p><p class='progress-label'>Overall Progress &mdash; {{ \$progress }}%</p><div class='progress-bar-bg'><div class='progress-bar-fill' style='width:{{ \$progress }}%'></div></div><a href='{{ \$ticket_url }}' class='btn'>View Full Progress</a></div><div class='footer'><p>Absterco CRM &bull; crm.absterco.com</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 6. ticket-resolved ──────────────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'ticket-resolved',
                'category'     => 'notification',
                'description'  => 'Sent to client when ticket is marked RESOLVED',
                'subject'      => 'Ticket #{{ $ticket_number }} has been resolved',
                'variables'    => [
                    ['name' => 'client_name',     'type' => 'string', 'description' => 'Recipient name',              'required' => true],
                    ['name' => 'ticket_number',   'type' => 'string', 'description' => 'Ticket reference',             'required' => true],
                    ['name' => 'ticket_title',    'type' => 'string', 'description' => 'Ticket title',                 'required' => true],
                    ['name' => 'resolution_note', 'type' => 'string', 'description' => 'Optional resolution summary',  'required' => false],
                    ['name' => 'staff_name',      'type' => 'string', 'description' => 'Staff who resolved it',        'required' => true],
                    ['name' => 'ticket_url',      'type' => 'url',    'description' => 'Link to ticket in portal',     'required' => true],
                    ['name' => 'portal_url',      'type' => 'url',    'description' => 'Client portal home URL',       'required' => true],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.resolved-banner{background:linear-gradient(135deg,#f0fdf4,#dcfce7);padding:24px 40px;text-align:center}.resolved-banner .icon{font-size:40px;line-height:1}.resolved-banner h2{margin:8px 0 4px;color:#15803d;font-size:20px;font-weight:700}.resolved-banner p{margin:0;color:#166534;font-size:14px}.body{padding:32px 40px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.resolution-box{background:#f9fafb;border-left:3px solid #84cc16;border-radius:0 8px 8px 0;padding:16px 20px;margin:16px 0;font-size:14px;color:#52525b;line-height:1.6}.btn{display:inline-block;background:#84cc16;color:#18181b;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px;margin-right:12px}.btn-ghost{display:inline-block;background:#f4f4f5;color:#18181b;text-decoration:none;font-weight:600;font-size:14px;padding:12px 28px;border-radius:8px}.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>Ticket Resolution</p></div><div class='resolved-banner'><div class='icon'>&#10003;</div><h2>Ticket Resolved</h2><p>Ticket #{{ \$ticket_number }}</p></div><div class='body'><p>Hi {{ \$client_name }}, <strong>{{ \$staff_name }}</strong> has resolved your ticket <strong>{{ \$ticket_title }}</strong>.</p>@if(!empty(\$resolution_note))<div class='resolution-box'>{{ \$resolution_note }}</div>@endif<p>If you feel the issue persists, you can reopen the ticket from your portal.</p><a href='{{ \$ticket_url }}' class='btn'>View Ticket</a><a href='{{ \$portal_url }}' class='btn-ghost'>Go to Portal</a></div><div class='footer'><p>Absterco CRM &bull; crm.absterco.com</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 7. invoice-issued ───────────────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'invoice-issued',
                'category'     => 'transactional',
                'description'  => 'Sent to client org owner when staff issues a new invoice',
                'subject'      => 'Invoice {{ $invoice_number }} — {{ $amount_formatted }} due {{ $due_date }}',
                'variables'    => [
                    ['name' => 'client_name',      'type' => 'string', 'description' => 'Recipient full name',              'required' => true],
                    ['name' => 'org_name',          'type' => 'string', 'description' => 'Organization name',                'required' => true],
                    ['name' => 'invoice_number',    'type' => 'string', 'description' => 'e.g. INV-2026-001',               'required' => true],
                    ['name' => 'invoice_title',     'type' => 'string', 'description' => 'Invoice description/title',        'required' => true],
                    ['name' => 'amount_formatted',  'type' => 'string', 'description' => 'e.g. $1,200.00',                  'required' => true],
                    ['name' => 'currency',          'type' => 'string', 'description' => 'e.g. USD',                        'required' => true],
                    ['name' => 'due_date',          'type' => 'string', 'description' => 'e.g. June 15, 2026',              'required' => true],
                    ['name' => 'line_items_html',   'type' => 'string', 'description' => 'Pre-rendered HTML table rows',     'required' => false],
                    ['name' => 'invoice_url',       'type' => 'url',    'description' => 'Link to invoice in client portal', 'required' => true],
                    ['name' => 'pay_url',           'type' => 'url',    'description' => 'Direct Stripe payment link',       'required' => true],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.invoice-header{padding:28px 40px;border-bottom:1px solid #f4f4f5;display:flex;justify-content:space-between;align-items:flex-start}.invoice-header .inv-num{font-size:24px;font-weight:700;color:#18181b}.invoice-header .inv-num span{font-size:13px;font-weight:400;color:#71717a;display:block;margin-bottom:4px}.invoice-header .amount{text-align:right}.invoice-header .amount .value{font-size:28px;font-weight:700;color:#18181b}.invoice-header .amount .currency{font-size:13px;color:#71717a}.body{padding:28px 40px}.due-badge{display:inline-block;background:#fef9c3;color:#854d0e;font-size:13px;font-weight:600;padding:6px 14px;border-radius:8px;margin-bottom:20px}.items-table{width:100%;border-collapse:collapse;margin:16px 0;font-size:14px}.items-table th{background:#f9fafb;padding:10px 12px;text-align:left;font-weight:600;color:#71717a;font-size:12px;text-transform:uppercase;letter-spacing:.5px}.items-table td{padding:12px;border-bottom:1px solid #f4f4f5;color:#52525b}.items-table tr:last-child td{border-bottom:none;font-weight:700;color:#18181b}.btn{display:inline-block;background:#84cc16;color:#18181b;text-decoration:none;font-weight:700;font-size:14px;padding:14px 32px;border-radius:8px;margin-right:12px}.btn-ghost{display:inline-block;background:#f4f4f5;color:#18181b;text-decoration:none;font-weight:600;font-size:14px;padding:14px 28px;border-radius:8px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>Invoice from Absterco</p></div><div class='invoice-header'><div class='inv-num'><span>Invoice Number</span>{{ \$invoice_number }}</div><div class='amount'><span class='currency'>{{ \$currency }}</span><div class='value'>{{ \$amount_formatted }}</div></div></div><div class='body'><div class='due-badge'>Due {{ \$due_date }}</div><p>Hi {{ \$client_name }}, a new invoice has been issued for <strong>{{ \$org_name }}</strong>: <strong>{{ \$invoice_title }}</strong>.</p>@if(!empty(\$line_items_html))<table class='items-table'><thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>{!! \$line_items_html !!}</tbody></table>@endif<p><a href='{{ \$pay_url }}' class='btn'>Pay Now</a><a href='{{ \$invoice_url }}' class='btn-ghost'>View Invoice</a></p></div><div class='footer'><p>Absterco CRM &bull; crm.absterco.com</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 8. invoice-payment-reminder ─────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'invoice-payment-reminder',
                'category'     => 'transactional',
                'description'  => 'Payment reminder sent 3 days before invoice due date',
                'subject'      => 'Reminder: Invoice {{ $invoice_number }} due in {{ $days_until_due }} days',
                'variables'    => [
                    ['name' => 'client_name',     'type' => 'string', 'description' => 'Recipient name',            'required' => true],
                    ['name' => 'org_name',         'type' => 'string', 'description' => 'Organization name',          'required' => true],
                    ['name' => 'invoice_number',   'type' => 'string', 'description' => 'e.g. INV-2026-001',         'required' => true],
                    ['name' => 'amount_formatted', 'type' => 'string', 'description' => 'e.g. $1,200.00',            'required' => true],
                    ['name' => 'due_date',         'type' => 'string', 'description' => 'e.g. June 15, 2026',        'required' => true],
                    ['name' => 'days_until_due',   'type' => 'number', 'description' => 'Days until due date',        'required' => true],
                    ['name' => 'pay_url',          'type' => 'url',    'description' => 'Direct Stripe payment link', 'required' => true],
                    ['name' => 'invoice_url',      'type' => 'url',    'description' => 'Link to invoice in portal',  'required' => true],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.reminder-bar{background:#fef9c3;border-bottom:2px solid #fde047;padding:14px 40px;font-weight:600;font-size:14px;color:#854d0e}.body{padding:32px 40px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.amount-box{background:#f9fafb;border-radius:10px;padding:20px 24px;margin:20px 0;text-align:center}.amount-box .label{font-size:13px;color:#71717a;margin:0 0 4px}.amount-box .amount{font-size:32px;font-weight:700;color:#18181b;margin:0 0 4px}.amount-box .due{font-size:14px;color:#854d0e;font-weight:600;margin:0}.btn{display:inline-block;background:#84cc16;color:#18181b;text-decoration:none;font-weight:700;font-size:14px;padding:14px 32px;border-radius:8px;margin-right:12px}.btn-ghost{display:inline-block;background:#f4f4f5;color:#18181b;text-decoration:none;font-weight:600;font-size:14px;padding:14px 28px;border-radius:8px}.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>Payment Reminder</p></div><div class='reminder-bar'>&#9888; Payment due in {{ \$days_until_due }} day{{ \$days_until_due == 1 ? '' : 's' }}</div><div class='body'><p>Hi {{ \$client_name }}, this is a friendly reminder that invoice <strong>{{ \$invoice_number }}</strong> for <strong>{{ \$org_name }}</strong> is coming up.</p><div class='amount-box'><p class='label'>Amount Due</p><p class='amount'>{{ \$amount_formatted }}</p><p class='due'>Due {{ \$due_date }}</p></div><p><a href='{{ \$pay_url }}' class='btn'>Pay Now</a><a href='{{ \$invoice_url }}' class='btn-ghost'>View Invoice</a></p></div><div class='footer'><p>Absterco CRM &bull; crm.absterco.com</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 9. invoice-overdue ──────────────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'invoice-overdue',
                'category'     => 'transactional',
                'description'  => 'Overdue notice sent 1 day after invoice due date passes',
                'subject'      => 'Overdue: Invoice {{ $invoice_number }} was due {{ $due_date }}',
                'variables'    => [
                    ['name' => 'client_name',     'type' => 'string', 'description' => 'Recipient name',            'required' => true],
                    ['name' => 'org_name',         'type' => 'string', 'description' => 'Organization name',          'required' => true],
                    ['name' => 'invoice_number',   'type' => 'string', 'description' => 'Invoice number',             'required' => true],
                    ['name' => 'amount_formatted', 'type' => 'string', 'description' => 'Amount due',                 'required' => true],
                    ['name' => 'due_date',         'type' => 'string', 'description' => 'Original due date',          'required' => true],
                    ['name' => 'days_overdue',     'type' => 'number', 'description' => 'Days past due',              'required' => true],
                    ['name' => 'pay_url',          'type' => 'url',    'description' => 'Direct Stripe payment link', 'required' => true],
                    ['name' => 'invoice_url',      'type' => 'url',    'description' => 'Link to invoice in portal',  'required' => true],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.overdue-bar{background:#fee2e2;border-bottom:2px solid #fca5a5;padding:14px 40px;font-weight:700;font-size:14px;color:#b91c1c}.body{padding:32px 40px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.amount-box{background:#fff5f5;border:1px solid #fca5a5;border-radius:10px;padding:20px 24px;margin:20px 0;text-align:center}.amount-box .label{font-size:13px;color:#b91c1c;font-weight:600;margin:0 0 4px}.amount-box .amount{font-size:32px;font-weight:700;color:#18181b;margin:0 0 4px}.amount-box .overdue-info{font-size:14px;color:#b91c1c;margin:0}.btn{display:inline-block;background:#ef4444;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:14px 32px;border-radius:8px;margin-right:12px}.btn-ghost{display:inline-block;background:#f4f4f5;color:#18181b;text-decoration:none;font-weight:600;font-size:14px;padding:14px 28px;border-radius:8px}.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>Overdue Invoice Notice</p></div><div class='overdue-bar'>&#9888; Payment overdue by {{ \$days_overdue }} day{{ \$days_overdue == 1 ? '' : 's' }}</div><div class='body'><p>Hi {{ \$client_name }}, invoice <strong>{{ \$invoice_number }}</strong> for <strong>{{ \$org_name }}</strong> was due on <strong>{{ \$due_date }}</strong> and is now overdue.</p><div class='amount-box'><p class='label'>Overdue Amount</p><p class='amount'>{{ \$amount_formatted }}</p><p class='overdue-info'>Was due {{ \$due_date }} &mdash; {{ \$days_overdue }} day{{ \$days_overdue == 1 ? '' : 's' }} ago</p></div><p>Please arrange payment as soon as possible to avoid any service interruptions. Contact us if you have any questions.</p><p><a href='{{ \$pay_url }}' class='btn'>Pay Now</a><a href='{{ \$invoice_url }}' class='btn-ghost'>View Invoice</a></p></div><div class='footer'><p>Absterco CRM &bull; crm.absterco.com</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 10. invoice-paid ────────────────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'invoice-paid',
                'category'     => 'transactional',
                'description'  => 'Payment confirmation sent to client when invoice is marked PAID',
                'subject'      => 'Payment received — Invoice {{ $invoice_number }}',
                'variables'    => [
                    ['name' => 'client_name',     'type' => 'string', 'description' => 'Recipient name',               'required' => true],
                    ['name' => 'org_name',         'type' => 'string', 'description' => 'Organization name',             'required' => true],
                    ['name' => 'invoice_number',   'type' => 'string', 'description' => 'Invoice number',                'required' => true],
                    ['name' => 'amount_formatted', 'type' => 'string', 'description' => 'Amount paid',                   'required' => true],
                    ['name' => 'payment_date',     'type' => 'string', 'description' => 'e.g. May 26, 2026',            'required' => true],
                    ['name' => 'payment_method',   'type' => 'string', 'description' => 'e.g. Visa ending 4242',        'required' => false],
                    ['name' => 'invoice_url',      'type' => 'url',    'description' => 'Link to invoice (receipt)',     'required' => true],
                    ['name' => 'portal_url',       'type' => 'url',    'description' => 'Client portal home',            'required' => true],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.paid-banner{background:linear-gradient(135deg,#f0fdf4,#dcfce7);padding:28px 40px;text-align:center}.paid-banner .icon{font-size:44px;line-height:1;margin-bottom:8px}.paid-banner h2{margin:0 0 4px;color:#15803d;font-size:22px;font-weight:700}.paid-banner p{margin:0;color:#166534;font-size:14px}.body{padding:32px 40px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.receipt-table{width:100%;border-collapse:collapse;margin:16px 0;background:#f9fafb;border-radius:10px;overflow:hidden}.receipt-table td{padding:12px 16px;font-size:14px;color:#52525b;border-bottom:1px solid #f4f4f5}.receipt-table td:first-child{font-weight:600;color:#18181b;width:160px}.receipt-table tr:last-child td{border-bottom:none}.btn{display:inline-block;background:#84cc16;color:#18181b;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px;margin-right:12px}.btn-ghost{display:inline-block;background:#f4f4f5;color:#18181b;text-decoration:none;font-weight:600;font-size:14px;padding:12px 28px;border-radius:8px}.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>Payment Confirmation</p></div><div class='paid-banner'><div class='icon'>&#10003;</div><h2>Payment Received</h2><p>Thank you for your payment</p></div><div class='body'><p>Hi {{ \$client_name }}, we've received your payment for <strong>{{ \$org_name }}</strong>. Here's your receipt:</p><table class='receipt-table'><tr><td>Invoice</td><td>{{ \$invoice_number }}</td></tr><tr><td>Amount Paid</td><td><strong>{{ \$amount_formatted }}</strong></td></tr><tr><td>Payment Date</td><td>{{ \$payment_date }}</td></tr>@if(!empty(\$payment_method))<tr><td>Payment Method</td><td>{{ \$payment_method }}</td></tr>@endif<tr><td>Status</td><td><strong style='color:#16a34a'>PAID</strong></td></tr></table><p><a href='{{ \$invoice_url }}' class='btn'>Download Receipt</a><a href='{{ \$portal_url }}' class='btn-ghost'>Go to Portal</a></p></div><div class='footer'><p>Absterco CRM &bull; crm.absterco.com</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 11. auth-otp ────────────────────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'auth-otp',
                'category'     => 'transactional',
                'description'  => 'One-time password / 2FA code for login or sensitive actions',
                'subject'      => 'Your Absterco CRM verification code',
                'variables'    => [
                    ['name' => 'name',       'type' => 'string', 'description' => 'Recipient name',                      'required' => true],
                    ['name' => 'otp',        'type' => 'string', 'description' => '6-digit OTP code',                    'required' => true],
                    ['name' => 'minutes',    'type' => 'number', 'description' => 'Minutes until code expires',           'required' => true],
                    ['name' => 'action',     'type' => 'string', 'description' => 'e.g. "sign in", "reset password"',     'required' => false],
                    ['name' => 'ip_address', 'type' => 'string', 'description' => 'IP address of the request (optional)', 'required' => false],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}.container{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}.header{background:#18181b;padding:32px 40px}.header h1{margin:0;color:#84cc16;font-size:22px;font-weight:700}.header p{margin:4px 0 0;color:#a1a1aa;font-size:13px}.body{padding:32px 40px}.body p{margin:0 0 16px;color:#52525b;font-size:15px;line-height:1.6}.otp-box{background:#18181b;border-radius:10px;padding:28px 24px;text-align:center;margin:24px 0}.otp-box .label{font-size:12px;font-weight:600;color:#a1a1aa;letter-spacing:2px;text-transform:uppercase;margin:0 0 12px}.otp-box .code{font-size:40px;font-weight:700;letter-spacing:12px;color:#84cc16;margin:0;font-variant-numeric:tabular-nums}.otp-box .expiry{font-size:13px;color:#71717a;margin:12px 0 0}.notice{background:#fafafa;border-left:3px solid #e4e4e7;border-radius:0 6px 6px 0;padding:12px 16px;margin-top:24px}.notice p{margin:0;font-size:13px;color:#71717a}@if(!empty(\$ip_address)).meta{font-size:12px;color:#a1a1aa;margin-top:8px}@endif.footer{background:#f4f4f5;padding:20px 40px;text-align:center}.footer p{margin:0;color:#a1a1aa;font-size:12px}</style></head><body><div class='container'><div class='header'><h1>Absterco CRM</h1><p>Security Verification</p></div><div class='body'><p>Hi {{ \$name }},</p><p>Use the code below to {{ !empty(\$action) ? \$action : 'verify your identity' }}. Do not share this code with anyone.</p><div class='otp-box'><p class='label'>Your verification code</p><p class='code'>{{ \$otp }}</p><p class='expiry'>Expires in {{ \$minutes }} minute{{ \$minutes == 1 ? '' : 's' }}</p></div><div class='notice'><p>&#128274; If you did not request this code, please ignore this email. Your account is still secure.</p>@if(!empty(\$ip_address))<p class='meta'>Request from IP: {{ \$ip_address }}</p>@endif</div></div><div class='footer'><p>Absterco CRM &bull; crm.absterco.com</p></div></div></body></html>",
                'status' => 'active',
            ],

            // ── 12. lead-outreach ───────────────────────────────────
            [
                'domain_id'    => $domainId,
                'template_key' => 'lead-outreach',
                'category'     => 'notification',
                'description'  => 'Staff-composed outreach email to a sales lead (plain body + open tracking pixel)',
                'subject'      => '{{ $subject }}',
                'variables'    => [
                    ['name' => 'subject',             'type' => 'string', 'description' => 'Email subject line',              'required' => true],
                    ['name' => 'body',                'type' => 'string', 'description' => 'Plain-text message body',         'required' => true],
                    ['name' => 'tracking_pixel_url',  'type' => 'url',    'description' => 'Open-tracking pixel (injected)',  'required' => false],
                ],
                'blade_html' => "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'><style>body{margin:0;padding:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#3f3f46;font-size:15px;line-height:1.7}</style></head><body><div>{!! nl2br(e(\$body)) !!}</div>@if(!empty(\$tracking_pixel_url))<img src=\"{{ \$tracking_pixel_url }}\" width=\"1\" height=\"1\" alt=\"\" border=\"0\" style=\"width:1px;height:1px;opacity:0;\" />@endif</body></html>",
                'status' => 'active',
            ],
        ];
    }
}
