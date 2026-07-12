<?php

namespace App\Console\Commands;

use App\Services\InboundImapService;
use Illuminate\Console\Command;

class DiagnoseInboundMail extends Command
{
    protected $signature = 'email:diagnose-inbound';

    protected $description = 'Inspect configured IMAP mailboxes (message counts, folders, recent mail)';

    public function handle(InboundImapService $service): int
    {
        try {
            $reports = $service->diagnose();
        } catch (\Throwable $e) {
            $this->error('Inbound diagnose failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        foreach ($reports as $report) {
            $this->newLine();
            if (empty($report['connected'])) {
                $this->error(sprintf(
                    'Mailbox %s — connection FAILED: %s',
                    $report['mailbox'] ?? 'unknown',
                    $report['error'] ?? 'unknown error'
                ));
                continue;
            }

            $domain = $report['domain'] ? " ({$report['domain']})" : '';
            $this->info(sprintf(
                'Mailbox %s%s on %s — folder %s',
                $report['mailbox'],
                $domain,
                $report['host'],
                $report['folder']
            ));
            $this->line(sprintf(
                '  INBOX: %d total, %d unread',
                $report['inbox_total'],
                $report['unseen_total']
            ));

            if (($report['inbox_total'] ?? 0) === 0) {
                $this->warn('  INBOX is empty — the prospect reply has not arrived at this mailbox.');
                $this->line('  Check: cPanel webmail, MX for email.absterco.com, spam folder, and that deal emails use Reply-To = staff mailbox (e.g. savindu@email.absterco.com).');
            }

            if (!empty($report['recent_messages'])) {
                $this->line('  Recent messages:');
                foreach ($report['recent_messages'] as $msg) {
                    $this->line(sprintf(
                        '    - %s | %s | %s',
                        $msg['date'] ?? 'no date',
                        $msg['from'] ?? 'unknown',
                        $msg['subject'] ?? '(no subject)'
                    ));
                }
            }

            if (!empty($report['non_empty_folders'])) {
                $this->line('  Other folders with mail:');
                foreach ($report['non_empty_folders'] as $folder) {
                    $this->line(sprintf('    - %s: %d message(s)', $folder['name'], $folder['messages']));
                }
            }
        }

        $this->newLine();
        return self::SUCCESS;
    }
}
