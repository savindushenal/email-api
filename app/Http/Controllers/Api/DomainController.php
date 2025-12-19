<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailDomain;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DomainController extends Controller
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * List all domains (Admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmailDomain::select([
            'id', 'domain', 'from_email', 'from_name',
            'mailer', 'status', 'daily_limit', 'hourly_limit', 'created_at'
        ]);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $domains = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'domains' => $domains,
                'count' => $domains->count(),
            ],
        ]);
    }

    /**
     * Get a single domain details
     */
    public function show(Request $request, string $domain): JsonResponse
    {
        $emailDomain = EmailDomain::where('domain', $domain)->first();

        if (!$emailDomain) {
            return response()->json([
                'success' => false,
                'message' => "Domain '{$domain}' not found",
            ], 404);
        }

        // Get template count
        $templateCount = $emailDomain->templates()->count();

        // Get email stats
        $emailsSent = $emailDomain->logs()->where('status', 'sent')->count();
        $emailsFailed = $emailDomain->logs()->where('status', 'failed')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'domain' => $emailDomain->only([
                    'id', 'domain', 'from_email', 'from_name',
                    'mailer', 'status', 'daily_limit', 'hourly_limit', 'created_at', 'updated_at'
                ]),
                'mail_config' => $emailDomain->mail_config ? [
                    'transport' => $emailDomain->mail_config['transport'] ?? 'smtp',
                    'host' => $emailDomain->mail_config['host'] ?? null,
                    'port' => $emailDomain->mail_config['port'] ?? null,
                    'encryption' => $emailDomain->mail_config['encryption'] ?? null,
                    // Don't expose username/password for security
                    'configured' => true,
                ] : ['configured' => false],
                'statistics' => [
                    'templates' => $templateCount,
                    'emails_sent' => $emailsSent,
                    'emails_failed' => $emailsFailed,
                ],
            ],
        ]);
    }

    /**
     * Create a new domain with email configuration
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string|max:255|unique:email_domains,domain',
            'from_email' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'mailer' => 'sometimes|in:smtp,ses,sendmail',
            'status' => 'sometimes|in:active,inactive',
            'daily_limit' => 'sometimes|integer|min:1|max:100000',
            'hourly_limit' => 'sometimes|integer|min:1|max:10000',
            // SMTP Configuration
            'mail_config' => 'sometimes|array',
            'mail_config.host' => 'required_with:mail_config|string',
            'mail_config.port' => 'required_with:mail_config|integer',
            'mail_config.encryption' => 'sometimes|in:ssl,tls,null',
            'mail_config.username' => 'required_with:mail_config|string',
            'mail_config.password' => 'required_with:mail_config|string',
            // SES Configuration (alternative)
            'ses_key' => 'sometimes|string',
            'ses_secret' => 'sometimes|string',
            'ses_region' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generate API key
        $apiKey = 'eak_' . Str::random(40);

        // Create domain
        $domain = EmailDomain::create([
            'domain' => $request->domain,
            'from_email' => $request->from_email,
            'from_name' => $request->from_name,
            'mailer' => $request->mailer ?? 'smtp',
            'status' => $request->status ?? 'active',
            'daily_limit' => $request->daily_limit ?? 1000,
            'hourly_limit' => $request->hourly_limit ?? 100,
            'api_key' => $apiKey,
            'ses_key' => $request->ses_key,
            'ses_secret' => $request->ses_secret,
            'ses_region' => $request->ses_region ?? 'us-east-1',
        ]);

        // Save mail config if provided
        if ($request->has('mail_config')) {
            $domain->mail_config = array_merge(
                ['transport' => 'smtp'],
                $request->mail_config
            );
            $domain->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Domain created successfully',
            'data' => [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'from_email' => $domain->from_email,
                'from_name' => $domain->from_name,
                'api_key' => $apiKey, // Only shown once!
                'status' => $domain->status,
                'mailer' => $domain->mailer,
                'daily_limit' => $domain->daily_limit,
                'hourly_limit' => $domain->hourly_limit,
            ],
            'warning' => 'Save the API key now! It will not be shown again.',
        ], 201);
    }

    /**
     * Update domain configuration
     */
    public function update(Request $request, string $domain): JsonResponse
    {
        $emailDomain = EmailDomain::where('domain', $domain)->first();

        if (!$emailDomain) {
            return response()->json([
                'success' => false,
                'message' => "Domain '{$domain}' not found",
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'from_email' => 'sometimes|email|max:255',
            'from_name' => 'sometimes|string|max:255',
            'mailer' => 'sometimes|in:smtp,ses,sendmail',
            'status' => 'sometimes|in:active,inactive,suspended',
            'daily_limit' => 'sometimes|integer|min:1|max:100000',
            'hourly_limit' => 'sometimes|integer|min:1|max:10000',
            // SMTP Configuration
            'mail_config' => 'sometimes|array',
            'mail_config.host' => 'required_with:mail_config|string',
            'mail_config.port' => 'required_with:mail_config|integer',
            'mail_config.encryption' => 'sometimes|in:ssl,tls,null',
            'mail_config.username' => 'required_with:mail_config|string',
            'mail_config.password' => 'required_with:mail_config|string',
            // SES Configuration
            'ses_key' => 'sometimes|string',
            'ses_secret' => 'sometimes|string',
            'ses_region' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update basic fields
        $emailDomain->fill($request->only([
            'from_email', 'from_name', 'mailer', 'status',
            'daily_limit', 'hourly_limit', 'ses_key', 'ses_secret', 'ses_region'
        ]));

        // Update mail config if provided
        if ($request->has('mail_config')) {
            $emailDomain->mail_config = array_merge(
                ['transport' => 'smtp'],
                $request->mail_config
            );
        }

        $emailDomain->save();

        return response()->json([
            'success' => true,
            'message' => 'Domain updated successfully',
            'data' => [
                'domain' => $emailDomain->domain,
                'from_email' => $emailDomain->from_email,
                'from_name' => $emailDomain->from_name,
                'status' => $emailDomain->status,
                'mailer' => $emailDomain->mailer,
                'daily_limit' => $emailDomain->daily_limit,
                'hourly_limit' => $emailDomain->hourly_limit,
                'mail_config_updated' => $request->has('mail_config'),
            ],
        ]);
    }

    /**
     * Regenerate API key for a domain
     */
    public function regenerateApiKey(Request $request, string $domain): JsonResponse
    {
        $emailDomain = EmailDomain::where('domain', $domain)->first();

        if (!$emailDomain) {
            return response()->json([
                'success' => false,
                'message' => "Domain '{$domain}' not found",
            ], 404);
        }

        // Generate new API key
        $newApiKey = 'eak_' . Str::random(40);
        $emailDomain->api_key = $newApiKey;
        $emailDomain->save();

        return response()->json([
            'success' => true,
            'message' => 'API key regenerated successfully',
            'data' => [
                'domain' => $emailDomain->domain,
                'api_key' => $newApiKey,
            ],
            'warning' => 'Save the new API key now! The old key is now invalid.',
        ]);
    }

    /**
     * Delete a domain
     */
    public function destroy(Request $request, string $domain): JsonResponse
    {
        $emailDomain = EmailDomain::where('domain', $domain)->first();

        if (!$emailDomain) {
            return response()->json([
                'success' => false,
                'message' => "Domain '{$domain}' not found",
            ], 404);
        }

        // Check if domain has templates
        $templateCount = $emailDomain->templates()->count();

        if ($templateCount > 0 && !$request->boolean('force')) {
            return response()->json([
                'success' => false,
                'message' => "Domain has {$templateCount} templates. Use ?force=true to delete anyway.",
                'data' => [
                    'template_count' => $templateCount,
                ],
            ], 409);
        }

        // Delete templates first if forcing
        if ($request->boolean('force')) {
            $emailDomain->templates()->delete();
        }

        $domainName = $emailDomain->domain;
        $emailDomain->delete();

        return response()->json([
            'success' => true,
            'message' => "Domain '{$domainName}' deleted successfully",
        ]);
    }

    /**
     * Test email configuration for a domain
     */
    public function testEmail(Request $request, string $domain): JsonResponse
    {
        $emailDomain = EmailDomain::where('domain', $domain)->first();

        if (!$emailDomain) {
            return response()->json([
                'success' => false,
                'message' => "Domain '{$domain}' not found",
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'to' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Create a simple test template inline
            $testHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email Configuration Test</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h1 style="color: #667eea; margin-top: 0;">🎉 Email Configuration Test</h1>
        <p style="font-size: 16px;">This is a test email from <strong>' . htmlspecialchars($emailDomain->domain) . '</strong></p>
        <p style="font-size: 16px;">If you received this email, your email configuration is working correctly!</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <table style="font-size: 14px; color: #666;">
            <tr><td style="padding: 5px 10px 5px 0;"><strong>Domain:</strong></td><td>' . htmlspecialchars($emailDomain->domain) . '</td></tr>
            <tr><td style="padding: 5px 10px 5px 0;"><strong>From:</strong></td><td>' . htmlspecialchars($emailDomain->from_email) . '</td></tr>
            <tr><td style="padding: 5px 10px 5px 0;"><strong>Mailer:</strong></td><td>' . htmlspecialchars($emailDomain->mailer) . '</td></tr>
            <tr><td style="padding: 5px 10px 5px 0;"><strong>Time:</strong></td><td>' . now()->toDateTimeString() . '</td></tr>
        </table>
    </div>
</body>
</html>';

            // Configure mailer using EmailService
            $this->emailService->configureMailerForTesting($emailDomain);

            // Send test email
            Mail::html($testHtml, function ($message) use ($emailDomain, $request) {
                $message->to($request->to)
                    ->from($emailDomain->from_email, $emailDomain->from_name)
                    ->subject('✅ Test Email from ' . $emailDomain->domain);
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully',
                'data' => [
                    'domain' => $emailDomain->domain,
                    'from' => $emailDomain->from_email,
                    'to' => $request->to,
                    'mailer' => $emailDomain->mailer,
                    'sent_at' => now()->toDateTimeString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email',
                'error' => $e->getMessage(),
                'hint' => 'Check your mail_config settings (host, port, credentials)',
            ], 500);
        }
    }

    /**
     * Get domain's API key (Admin only, for recovery)
     */
    public function getApiKey(Request $request, string $domain): JsonResponse
    {
        $emailDomain = EmailDomain::where('domain', $domain)->first();

        if (!$emailDomain) {
            return response()->json([
                'success' => false,
                'message' => "Domain '{$domain}' not found",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'domain' => $emailDomain->domain,
                'api_key' => $emailDomain->api_key,
            ],
            'warning' => 'Keep this API key secure. Do not share it publicly.',
        ]);
    }
}
