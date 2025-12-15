<?php

namespace App\Services;

use App\Models\EmailDomain;
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use App\Mail\DynamicTemplateMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Exception;

class EmailService
{
    /**
     * Send email using the appropriate mailer.
     *
     * @param EmailDomain $domain
     * @param string $templateKey
     * @param string $toEmail
     * @param array $data
     * @return array
     */
    public function send(
        EmailDomain $domain,
        string $templateKey,
        string $toEmail,
        array $data
    ): array {
        try {
            // Validate domain is active
            if (!$domain->isActive()) {
                throw new Exception('Domain is not active');
            }

            // Check rate limits
            $rateLimit = $domain->checkRateLimit();
            if (!$rateLimit['allowed']) {
                throw new Exception($rateLimit['message']);
            }

            // Get template
            $template = EmailTemplate::forDomain($domain->id, $templateKey)
                ->active()
                ->first();
            
            if (!$template) {
                throw new Exception("Template '{$templateKey}' not found for domain '{$domain->domain}'");
            }

            // Render Blade template with data
            $renderedHtml = $this->renderBladeTemplate($template->blade_html, $data);
            $renderedSubject = $this->renderBladeTemplate($template->subject, $data);

            // Create email log entry
            $emailLog = EmailLog::create([
                'domain_id' => $domain->id,
                'template_id' => $template->id,
                'from_email' => $domain->from_email,
                'to_email' => $toEmail,
                'subject' => $renderedSubject,
                'template_key' => $templateKey,
                'status' => 'queued',
                'mailer_used' => $domain->mailer,
                'variables' => $data,
            ]);

            // Configure mailer based on domain settings
            $this->configureMailer($domain);

            // Create and send email
            $mailable = new DynamicTemplateMail(
                $renderedHtml,
                $renderedSubject,
                $domain->from_email,
                $domain->from_name
            );

            Mail::to($toEmail)->send($mailable);

            // Mark as sent
            $messageId = $this->generateMessageId();
            $emailLog->markAsSent($messageId);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'data' => [
                    'log_id' => $emailLog->id,
                    'message_id' => $messageId,
                    'to' => $toEmail,
                    'from' => $domain->from_email,
                    'subject' => $renderedSubject,
                    'sent_at' => $emailLog->sent_at->toIso8601String(),
                    'mailer' => $domain->mailer,
                ],
            ];

        } catch (Exception $e) {
            // Log the error if log exists
            if (isset($emailLog)) {
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

    /**
     * Render Blade template with data.
     *
     * @param string $bladeTemplate
     * @param array $data
     * @return string
     */
    protected function renderBladeTemplate(string $bladeTemplate, array $data): string
    {
        try {
            return Blade::render($bladeTemplate, $data);
        } catch (Exception $e) {
            throw new Exception("Template rendering failed: " . $e->getMessage());
        }
    }

    /**
     * Configure the mail driver based on domain settings.
     *
     * @param EmailDomain $domain
     * @return void
     */
    protected function configureMailer(EmailDomain $domain): void
    {
        if ($domain->usesSes()) {
            // Configure SES
            Config::set('mail.default', 'ses');
            Config::set('mail.mailers.ses.transport', 'ses');
            
            // Set AWS credentials for this domain
            Config::set('services.ses.key', $domain->ses_key);
            Config::set('services.ses.secret', $domain->ses_secret);
            Config::set('services.ses.region', $domain->ses_region);
        } else {
            // Use sendmail (default for cPanel)
            Config::set('mail.default', 'sendmail');
            Config::set('mail.mailers.sendmail.transport', 'sendmail');
            Config::set('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i');
        }

        // Set from address
        Config::set('mail.from.address', $domain->from_email);
        Config::set('mail.from.name', $domain->from_name);
    }

    /**
     * Generate a unique message ID for tracking.
     *
     * @return string
     */
    protected function generateMessageId(): string
    {
        return 'eak_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Validate email address.
     *
     * @param string $email
     * @return bool
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Get sending statistics for a domain.
     *
     * @param EmailDomain $domain
     * @param string $period (today, week, month)
     * @return array
     */
    public function getStats(EmailDomain $domain, string $period = 'today'): array
    {
        $startDate = match($period) {
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
}
