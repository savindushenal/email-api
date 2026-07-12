<?php

namespace App\Console\Commands;

use App\Services\InboundImapService;
use Illuminate\Console\Command;

class PollInboundMail extends Command
{
    protected $signature = 'email:poll-inbound';

    protected $description = 'Poll IMAP inbox for deal email replies and forward to CRM';

    public function handle(InboundImapService $service): int
    {
        try {
            $stats = $service->poll();
            $this->info(sprintf(
                'Inbound poll complete — mailboxes: %d, processed: %d, forwarded: %d, skipped: %d, errors: %d',
                $stats['mailboxes'],
                $stats['processed'],
                $stats['forwarded'],
                $stats['skipped'],
                $stats['errors']
            ));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Inbound poll failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
