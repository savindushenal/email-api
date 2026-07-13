<?php

namespace App\Console\Commands;

use App\Services\InboundImapService;
use Illuminate\Console\Command;

class PollInboundMail extends Command
{
    protected $signature = 'email:poll-inbound
                            {--unread-only : Only process unread messages (skip read mail in INBOX)}
                            {--days=30 : Days to look back when importing read messages}';

    protected $description = 'Poll IMAP inbox for deal email replies and forward to CRM';

    public function handle(InboundImapService $service): int
    {
        $includeSeen = !$this->option('unread-only');
        $days = max(1, (int) $this->option('days'));

        try {
            $stats = $service->poll($includeSeen, $days);
            $this->info(sprintf(
                'Inbound poll complete — mailboxes: %d, processed: %d, forwarded: %d, already in CRM: %d, unmatched: %d, skipped: %d, errors: %d%s',
                $stats['mailboxes'],
                $stats['processed'],
                $stats['forwarded'],
                $stats['already_forwarded'] ?? 0,
                $stats['unmatched'] ?? 0,
                $stats['skipped'],
                $stats['errors'],
                $includeSeen ? sprintf(' (last %d days + unread)', $days) : ' (unread only)'
            ));

            foreach ($stats['details'] as $detail) {
                $domain = $detail['domain'] ? " ({$detail['domain']})" : '';
                $this->line(sprintf(
                    '  %s%s — %d candidate message(s), INBOX total: %d, unread: %d',
                    $detail['mailbox'],
                    $domain,
                    $detail['candidates'],
                    $detail['inbox_total'] ?? 0,
                    $detail['unseen_total'] ?? 0
                ));
            }

            if ($stats['processed'] === 0) {
                $inboxEmpty = collect($stats['details'])->every(
                    fn (array $detail) => ($detail['inbox_total'] ?? 0) === 0
                );
                if ($inboxEmpty) {
                    $this->warn(
                        'INBOX is empty — the reply has not been delivered to the staff mailbox yet. Run php artisan email:diagnose-inbound for folder details.'
                    );
                } elseif ($includeSeen) {
                    $this->warn(
                        'INBOX has messages but none matched the search window — try --days=60 or check storage/logs/laravel.log.'
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
            } elseif ($stats['forwarded'] === 0 && ($stats['already_forwarded'] ?? 0) > 0) {
                $this->line('All candidate messages were already imported to CRM.');
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
