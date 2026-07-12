<?php

namespace App\Services;

use App\Models\EmailDomain;
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use App\Models\StaffMailbox;
use App\Mail\DynamicTemplateMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Exception;

class EmailService
{
    public function __construct(
        protected DomainMailConfigService $mailConfigService
    ) {
    }

    public function send(
        EmailDomain $domain,
        string $templateKey,
        string $toEmail,
        array $data,
        ?EmailSendOptions $options = null
    ): array {
        $options ??= new EmailSendOptions();
        $emailLog = null;

        try {
            if (!$domain->isActive()) {
                throw new Exception('Domain is not active');
            }

            $rateLimit = $domain->checkRateLimit();
            if (!$rateLimit['allowed']) {
                throw new Exception($rateLimit['message']);
            }

            $template = EmailTemplate::forDomain($domain->id, $templateKey)
                ->active()
                ->first();

            if (!$template) {
                throw new Exception("Template '{$templateKey}' not found for domain '{$domain->domain}'");
            }

            $fromEmail = $options->fromEmail ?: $domain->from_email;
            $fromName = $options->fromName ?: $domain->from_name;
            $this->assertEmailOnDomain($fromEmail, $domain->domain);

            $staffMailbox = null;
            if ($options->fromEmail) {
                $staffMailbox = StaffMailbox::query()
                    ->where('email_domain_id', $domain->id)
                    ->where('email', strtolower($options->fromEmail))
                    ->active()
                    ->first();
            }

            $renderedHtml = $this->renderBladeTemplate($template->blade_html, $data);
            $renderedSubject = $this->renderBladeTemplate($template->subject, $data);

            if (!empty($data['tracking_pixel_url'])) {
                $pixelUrl = trim((string) $data['tracking_pixel_url']);
                $alreadyHasPixel = stripos($renderedHtml, '/api/public/email/t/') !== false;
                if ($pixelUrl !== '' && preg_match('#^https?://#i', $pixelUrl) && !$alreadyHasPixel) {
                    $pixel = '<img src="' . e($pixelUrl) . '" width="1" height="1" alt="" border="0" style="width:1px;height:1px;opacity:0;" />';
                    if (stripos($renderedHtml, '</body>') !== false) {
                        $renderedHtml = str_ireplace('</body>', $pixel . '</body>', $renderedHtml);
                    } else {
                        $renderedHtml .= $pixel;
                    }
                }
            }

            $cc = $this->normalizeRecipientList($options->cc, $toEmail);
            $bcc = $this->normalizeRecipientList($options->bcc, $toEmail, $cc);
            $replyTo = $options->replyTo ? trim($options->replyTo) : null;
            if ($replyTo !== null && $replyTo !== '' && !$this->validateEmail($replyTo)) {
                throw new Exception('Invalid reply_to address');
            }

            $emailLog = EmailLog::create([
                'domain_id' => $domain->id,
                'template_id' => $template->id,
                'from_email' => $fromEmail,
                'to_email' => $toEmail,
                'subject' => $renderedSubject,
                'template_key' => $templateKey,
                'status' => 'queued',
                'mailer_used' => $domain->mailer,
                'variables' => $data,
            ]);

            if ($staffMailbox) {
                $this->configureMailerFromMailbox($staffMailbox);
            } else {
                $this->configureMailer($domain);
            }

            $messageIdHeader = $this->generateMessageId($domain->domain);

            $mailable = new DynamicTemplateMail(
                $renderedHtml,
                $renderedSubject,
                $fromEmail,
                $fromName,
                $cc,
                $bcc,
                $replyTo ?: null,
                $messageIdHeader
            );

            Mail::to($toEmail)->send($mailable);

            $emailLog->markAsSent($messageIdHeader);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'data' => [
                    'log_id' => $emailLog->id,
                    'message_id' => $messageIdHeader,
                    'to' => $toEmail,
                    'from' => $fromEmail,
                    'subject' => $renderedSubject,
                    'sent_at' => $emailLog->sent_at->toIso8601String(),
                    'mailer' => $domain->mailer,
                ],
            ];
        } catch (Exception $e) {
            if ($emailLog) {
                $emailLog->markAsFailed($e->getMessage());
            }

            return [
                'success' => false,
                'message' => 'Email sending failed',
                'error' => $e->getMessage(),
                'data' => [
                    'log_id' => $emailLog->id ?? null,
                ],
            ];
        }
    }

    protected function assertEmailOnDomain(string $email, string $domain): void
    {
        $parts = explode('@', strtolower($email));
        if (count($parts) !== 2 || $parts[1] !== strtolower($domain)) {
            throw new Exception("from_email must be an address on {$domain}");
        }
    }

    protected function renderBladeTemplate(string $bladeTemplate, array $data): string
    {
        try {
            return Blade::render($bladeTemplate, $data);
        } catch (Exception $e) {
            throw new Exception('Template rendering failed: ' . $e->getMessage());
        }
    }

    protected function configureMailer(EmailDomain $domain): void
    {
        $mailConfig = $domain->mail_config ?? [];
        $transport = $mailConfig['transport'] ?? 'smtp';

        if ($transport === 'ses' && $domain->usesSes()) {
            Config::set('mail.default', 'ses');
            Config::set('mail.mailers.ses.transport', 'ses');
            Config::set('services.ses.key', $domain->ses_key);
            Config::set('services.ses.secret', $domain->ses_secret);
            Config::set('services.ses.region', $domain->ses_region);
        } elseif ($transport === 'smtp' && isset($mailConfig['host'])) {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp', [
                'transport' => 'smtp',
                'host' => $mailConfig['host'],
                'port' => $mailConfig['port'] ?? 465,
                'encryption' => $mailConfig['encryption'] ?? 'ssl',
                'username' => $mailConfig['username'] ?? null,
                'password' => $this->mailConfigService->smtpPassword($mailConfig),
                'timeout' => null,
            ]);
        } else {
            Config::set('mail.default', 'smtp');
        }

        Config::set('mail.from.address', $domain->from_email);
        Config::set('mail.from.name', $domain->from_name);
    }

    protected function configureMailerFromMailbox(StaffMailbox $mailbox): void
    {
        $smtp = $mailbox->smtpConfig();

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'encryption' => $smtp['encryption'] === 'null' ? null : $smtp['encryption'],
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            'timeout' => null,
        ]);

        Config::set('mail.from.address', $mailbox->email);
        Config::set('mail.from.name', $mailbox->display_name ?: $mailbox->email);
    }

    protected function generateMessageId(string $domain): string
    {
        return sprintf('<%s@%s>', 'eak_' . bin2hex(random_bytes(16)), $domain);
    }

    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param string[] $emails
     * @param string[] $extraExclude
     * @return string[]
     */
    protected function normalizeRecipientList(array $emails, string $toEmail, array $extraExclude = []): array
    {
        $exclude = array_map('strtolower', array_merge([$toEmail], $extraExclude));
        $seen = [];
        $normalized = [];

        foreach ($emails as $email) {
            if (!is_string($email)) {
                continue;
            }
            $trimmed = strtolower(trim($email));
            if ($trimmed === '' || !$this->validateEmail($trimmed)) {
                continue;
            }
            if (in_array($trimmed, $exclude, true) || isset($seen[$trimmed])) {
                continue;
            }
            $seen[$trimmed] = true;
            $normalized[] = $trimmed;
        }

        return $normalized;
    }

    public function getStats(EmailDomain $domain, string $period = 'today'): array
    {
        $startDate = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };

        $logs = $domain->logs()->where('created_at', '>=', $startDate);

        return [
            'total' => $logs->count(),
            'sent' => $logs->where('status', 'sent')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'queued' => $logs->where('status', 'queued')->count(),
            'period' => $period,
            'start_date' => $startDate->toIso8601String(),
        ];
    }

    public function configureMailerForTesting(EmailDomain $domain): void
    {
        $this->configureMailer($domain);
    }
}
