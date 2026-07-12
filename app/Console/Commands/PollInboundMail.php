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
                    '  %s%s — %d candidate message(s)',
                    $detail['mailbox'],
                    $domain,
                    $detail['candidates']
                ));
            }

            if ($stats['processed'] === 0) {
                $this->warn(
                    'No messages to process. By default only UNSEEN mail is polled — if you opened the reply in webmail, re-run with --include-seen.'
                );
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
