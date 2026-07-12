<?php

namespace App\Console\Commands;

use App\Models\StaffMailbox;
use App\Services\InboundMailService;
use Illuminate\Console\Command;

class PollInboundCommand extends Command
{
    protected $signature = 'email:poll-inbound {--mailbox= : Poll a single mailbox email address}';

    protected $description = 'Poll staff mailboxes for inbound deal replies and forward them to the CRM webhook';

    public function handle(InboundMailService $inboundMailService): int
    {
        $query = StaffMailbox::query()->active()->with('domain');

        if ($mailboxEmail = $this->option('mailbox')) {
            $query->where('email', strtolower($mailboxEmail));
        }

        $mailboxes = $query->get();

        if ($mailboxes->isEmpty()) {
            $this->warn('No active staff mailboxes to poll.');
            return self::SUCCESS;
        }

        $totalProcessed = 0;
        $hadErrors = false;

        foreach ($mailboxes as $mailbox) {
            $this->line("Polling {$mailbox->email}...");

            try {
                $result = $inboundMailService->pollMailbox($mailbox);
                $totalProcessed += $result['processed'];
                $this->info("  processed={$result['processed']} skipped={$result['skipped']}");

                foreach ($result['errors'] as $error) {
                    $hadErrors = true;
                    $this->error("  {$error}");
                }
            } catch (\Throwable $e) {
                $hadErrors = true;
                $this->error("  {$mailbox->email}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Total processed: {$totalProcessed}");

        return $hadErrors ? self::FAILURE : self::SUCCESS;
    }
}
