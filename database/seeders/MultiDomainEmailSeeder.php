<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailDomain;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MultiDomainEmailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Seeding multiple domains with SMTP configurations...');
        $this->command->info('');

        // Clear existing data
        EmailDomain::whereIn('domain', ['menuvire.com', 'fitvire.com'])->delete();
        $this->command->info('Cleared existing domains...');
        $this->command->info('');

        // Domain 1: MenuVire Platform
        $apiKey1 = 'eak_' . Str::random(40);
        $menuvire = EmailDomain::create([
            'domain' => 'menuvire.com',
            'api_key' => hash('sha256', $apiKey1),
            'from_email' => 'no-reply@menuvire.com',
            'from_name' => 'MenuVire',
            'status' => 'active',
            'mailer' => 'exim', // Will configure SMTP via model accessor
            'daily_limit' => 1000,
            'hourly_limit' => 100,
        ]);

        // Store SMTP config in model's mail_config attribute (if supported)
        $menuvire->mail_config = [
            'transport' => 'smtp',
            'host' => 'uniform.de.hostns.io',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'no-reply@menuvire.com',
            'password' => 'menuvire@w',
        ];
        $menuvire->save();

        $this->command->info("✅ Domain: {$menuvire->domain}");
        $this->command->info("   From: {$menuvire->from_email}");
        $this->command->info("   API Key: {$apiKey1}");
        $this->command->info('');

        // Domain 2: FitVire Platform
        $apiKey2 = 'eak_' . Str::random(40);
        $fitvire = EmailDomain::create([
            'domain' => 'fitvire.com',
            'api_key' => hash('sha256', $apiKey2),
            'from_email' => 'no-reply@fitvire.com',
            'from_name' => 'FitVire',
            'status' => 'active',
            'mailer' => 'exim',
            'daily_limit' => 1000,
            'hourly_limit' => 100,
        ]);

        $fitvire->mail_config = [
            'transport' => 'smtp',
            'host' => 'uniform.de.hostns.io',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'no-reply@fitvire.com',
            'password' => 'fitvire_password', // Update with actual password
        ];
        $fitvire->save();

        $this->command->info("✅ Domain: {$fitvire->domain}");
        $this->command->info("   From: {$fitvire->from_email}");
        $this->command->info("   API Key: {$apiKey2}");
        $this->command->info('');

        // Create Welcome Email Templates
        $this->createTemplates($menuvire, 'MenuVire');
        $this->createTemplates($fitvire, 'FitVire');

        $this->command->newLine();
        $this->command->info('✅ Multi-domain seeding completed!');
        $this->command->newLine();
        $this->command->table(
            ['Domain', 'From Email', 'Mailer', 'Templates', 'API Key'],
            [
                [$menuvire->domain, $menuvire->from_email, $menuvire->mailer, '3', substr($apiKey1, 0, 20).'...'],
                [$fitvire->domain, $fitvire->from_email, $fitvire->mailer, '3', substr($apiKey2, 0, 20).'...'],
            ]
        );
        
        $this->command->newLine();
        $this->command->warn('⚠️  Save these API keys - they won\'t be shown again:');
        $this->command->info("MenuVire: {$apiKey1}");
        $this->command->info("FitVire: {$apiKey2}");
    }

    /**
     * Create email templates for a domain.
     */
    protected function createTemplates(EmailDomain $domain, string $platformName): void
    {
        // Welcome template
        EmailTemplate::create([
            'domain_id' => $domain->id,
            'template_key' => 'welcome',
            'subject' => 'Welcome to ' . $platformName . ', {{ $user_name }}!',
            'blade_html' => $this->getWelcomeTemplate(),
            'status' => 'active',
        ]);

        // Password Reset template
        EmailTemplate::create([
            'domain_id' => $domain->id,
            'template_key' => 'password-reset',
            'subject' => 'Reset Your Password for {{ $platform_name }}',
            'blade_html' => $this->getPasswordResetTemplate(),
            'status' => 'active',
        ]);

        // OTP template
        EmailTemplate::create([
            'domain_id' => $domain->id,
            'template_key' => 'otp',
            'subject' => 'Your {{ $platform_name }} Verification Code',
            'blade_html' => $this->getOTPTemplate(),
            'status' => 'active',
        ]);

        $this->command->info("   Created 3 templates for {$domain->domain}");
    }    protected function getWelcomeTemplate(): string
    {
        return <<<'BLADE'
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{ $platform_name }}!</h1>
        </div>
        <div class="content">
            <h2>Hello {{ $user_name }},</h2>
            <p>We're thrilled to have you join our platform! Your account has been successfully created.</p>
            
            @if(isset($verification_link))
            <p>To get started, please verify your email address:</p>
            <a href="{{ $verification_link }}" class="button">Verify Email</a>
            @endif
            
            <p>Here's what you can do next:</p>
            <ul>
                <li>Complete your profile</li>
                <li>Explore our features</li>
                <li>Connect with other users</li>
            </ul>
            
            <p>If you have any questions, feel free to reach out to our support team.</p>
            
            <p>Best regards,<br>{{ $platform_name }} Team</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $platform_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
BLADE;
    }

    protected function getVerificationTemplate(): string
    {
        return <<<'BLADE'
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4CAF50; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; background: #4CAF50; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
        .code { background: #fff; border: 2px dashed #4CAF50; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verify Your Email</h1>
        </div>
        <div class="content">
            <h2>Hello {{ $user_name }},</h2>
            <p>Thank you for signing up! Please verify your email address to activate your account.</p>
            
            @if(isset($verification_link))
            <a href="{{ $verification_link }}" class="button">Verify Email Address</a>
            @endif
            
            @if(isset($verification_code))
            <p>Or use this verification code:</p>
            <div class="code">{{ $verification_code }}</div>
            @endif
            
            <p>This link will expire in {{ $expiry_hours ?? 24 }} hours.</p>
            
            <p>If you didn't create an account, please ignore this email.</p>
        </div>
    </div>
</body>
</html>
BLADE;
    }

    protected function getPasswordResetTemplate(): string
    {
        return <<<'BLADE'
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #FF5722; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; background: #FF5722; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
        .warning { background: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔒 Password Reset Request</h1>
        </div>
        <div class="content">
            <h2>Hello {{ $user_name }},</h2>
            <p>We received a request to reset your password. Click the button below to create a new password:</p>
            
            <a href="{{ $reset_link }}" class="button">Reset Password</a>
            
            <p>This link will expire in {{ $expiry_hours ?? 1 }} hour(s).</p>
            
            <div class="warning">
                <strong>⚠️ Security Notice:</strong> If you didn't request this password reset, please ignore this email. Your password will remain unchanged.
            </div>
            
            <p>For security reasons, never share your password with anyone.</p>
        </div>
    </div>
</body>
</html>
BLADE;
    }

    protected function getOTPTemplate(): string
    {
        return <<<'BLADE'
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; text-align: center; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4CAF50; color: white; padding: 30px; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .otp-code { font-size: 36px; font-weight: bold; letter-spacing: 8px; background: white; padding: 20px; border: 2px dashed #4CAF50; border-radius: 10px; margin: 20px 0; color: #4CAF50; }
        .info { background: #e3f2fd; padding: 15px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Verification Code</h1>
        </div>
        <div class="content">
            <p>Your verification code is:</p>
            <div class="otp-code">{{ $otp_code }}</div>
            <div class="info">
                <p>This code expires in {{ $validity ?? 10 }} minutes</p>
                <p>Never share this code with anyone</p>
            </div>
            <p>If you didn't request this code, please ignore this email.</p>
        </div>
    </div>
</body>
</html>
BLADE;
    }
}
