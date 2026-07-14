<?php

namespace App\Console\Commands;

use App\Services\InboundImapService;
use Illuminate\Console\Command;

class PollInboundMail extends Command
{
    protected $signature = 'email:poll-inbound
                            {--unread-only : Only process unread messages (skip read mail in INBOX/Junk)}
                            {--days=30 : Days to look back when importing read messages}
                            {--force : Re-forward to CRM even if Message-ID was already marked processed}
                            {--verbose-messages : Print each candidate From/Subject/Message-ID/status}';

    protected $description = 'Poll IMAP inbox (+ Junk/Spam) for deal email replies and forward to CRM';

    public function handle(InboundImapService $service): int
    {
        $includeSeen = !$this->option('unread-only');
        $days = max(1, (int) $this->option('days'));
        $force = (bool) $this->option('force');

        try {
            $stats = $service->poll($includeSeen, $days, $force);
            $this->info(sprintf(
                'Inbound poll complete — mailboxes: %d, processed: %d, forwarded: %d, CRM duplicates: %d, already in CRM: %d, unmatched: %d, skipped: %d, errors: %d%s%s',
                $stats['mailboxes'],
                $stats['processed'],
                $stats['forwarded'],
                $stats['duplicates'] ?? 0,
                $stats['already_forwarded'] ?? 0,
                $stats['unmatched'] ?? 0,
                $stats['skipped'],
                $stats['errors'],
                $includeSeen ? sprintf(' (last %d days + unread)', $days) : ' (unread only)',
                $force ? ' [FORCE]' : ''
            ));

            foreach ($stats['details'] as $detail) {
                $domain = $detail['domain'] ? " ({$detail['domain']})" : '';
                $folders = $detail['folders'] ?? [$detail['folder'] ?? 'INBOX'];
                $this->line(sprintf(
                    '  %s%s — %d candidate message(s), folders: %s',
                    $detail['mailbox'],
                    $domain,
                    $detail['candidates'],
                    implode(', ', $folders)
                ));
                foreach ($detail['folder_totals'] ?? [] as $folderName => $totals) {
                    $this->line(sprintf(
                        '    %s — total: %d, unread: %d',
                        $folderName,
                        $totals['total'] ?? 0,
                        $totals['unseen'] ?? 0
                    ));
                }
            }

            if ($this->option('verbose-messages') || $force) {
                foreach ($stats['messages'] ?? [] as $msg) {
                    $this->line(sprintf(
                        '    [%s] [%s] %s | %s | mid=%s | irt=%s',
                        $msg['status'] ?? '?',
                        $msg['folder'] ?? 'INBOX',
                        $msg['from'] ?? '?',
                        $msg['subject'] ?? '(no subject)',
                        $msg['messageId'] ?? '(none)',
                        $msg['inReplyTo'] ?? '(none)'
                    ));
                }
            }

            if ($stats['processed'] === 0) {
                $allEmpty = collect($stats['details'])->every(function (array $detail) {
                    $folderTotals = $detail['folder_totals'] ?? [];
                    if ($folderTotals === []) {
                        return ($detail['inbox_total'] ?? 0) === 0;
                    }
                    return collect($folderTotals)->every(fn (array $t) => ($t['total'] ?? 0) === 0);
                });
                if ($allEmpty) {
                    $this->warn(
                        'INBOX and Junk/Spam are empty — the reply has not been delivered to the staff mailbox yet. Run php artisan email:diagnose-inbound for folder details.'
                    );
                } elseif ($includeSeen) {
                    $this->warn(
                        'Folders have messages but none matched the search window — try --days=60 or check storage/logs/laravel.log.'
                    );
                } else {
                    $this->warn(
                        'No unread messages — re-run without --unread-only to import read mail from the last 30 days.'
                    );
                }
            } elseif ($stats['forwarded'] === 0 && ($stats['unmatched'] ?? 0) > 0) {
                $this->warn('CRM received replies but could not match them to a deal — check deal_emails.message_id / reply_token and storage/logs/laravel.log.');
            } elseif ($stats['forwarded'] === 0 && $stats['errors'] > 0) {
                $this->warn('Messages were found but forwarding failed — check storage/logs/laravel.log for [imap] entries.');
            } elseif ($stats['forwarded'] === 0 && ($stats['duplicates'] ?? 0) > 0) {
                $this->warn(
                    'CRM already has these Message-IDs in deal_email_replies. If a reply is missing in mailroom, it is a UI/threading issue — not an IMAP import skip.'
                );
            } elseif ($stats['forwarded'] === 0 && ($stats['already_forwarded'] ?? 0) > 0) {
                $this->line('All candidate messages were already imported to CRM.');
                $this->line('If a reply is missing, re-run with: php artisan email:poll-inbound --force');
            } elseif ($stats['forwarded'] === 0 && $stats['skipped'] > 0) {
                $this->warn('Messages were skipped (invalid From or empty body after MIME parse) — see storage/logs/laravel.log for [imap] Skipped message entries.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Inbound poll failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
