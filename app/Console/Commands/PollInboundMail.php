<?php

namespace App\Console\Commands;

use App\Services\InboundImapService;
use Illuminate\Console\Command;

class PollInboundMail extends Command
{
    protected $signature = 'email:poll-inbound
                            {--include-seen : Also process read messages from the lookback window}
                            {--days=7 : Days to look back when --include-seen is set}';

    protected $description = 'Poll IMAP inbox for deal email replies and forward to CRM';

    public function handle(InboundImapService $service): int
    {
        $includeSeen = (bool) $this->option('include-seen');
        $days = max(1, (int) $this->option('days'));

        try {
            $stats = $service->poll($includeSeen, $days);
            $this->info(sprintf(
                'Inbound poll complete — mailboxes: %d, processed: %d, forwarded: %d, unmatched: %d, skipped: %d, errors: %d%s',
                $stats['mailboxes'],
                $stats['processed'],
                $stats['forwarded'],
                $stats['unmatched'] ?? 0,
                $stats['skipped'],
                $stats['errors'],
                $includeSeen ? sprintf(' (include-seen, last %d days)', $days) : ' (unread only)'
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
                        'INBOX has messages but none matched the search window — try --days=30 or check storage/logs/laravel.log.'
                    );
                } else {
                    $this->warn(
                        'No unread messages — if you opened the reply in webmail, re-run with --include-seen.'
                    );
                }
            } elseif ($stats['forwarded'] === 0 && ($stats['unmatched'] ?? 0) > 0) {
                $this->warn('CRM received replies but could not match them to a deal — check deal_emails.message_id / reply_token and storage/logs/laravel.log.');
            } elseif ($stats['forwarded'] === 0 && $stats['errors'] > 0) {
                $this->warn('Messages were found but forwarding failed — check storage/logs/laravel.log for [imap] entries.');
            } elseif ($stats['forwarded'] === 0 && $stats['skipped'] > 0) {
                $this->warn('Messages were skipped (missing from/subject/body) — check the mailbox has the prospect reply.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Inbound poll failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
