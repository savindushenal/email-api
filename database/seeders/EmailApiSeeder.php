<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailDomain;
use App\Models\EmailTemplate;

class EmailApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample domain
        $domain = EmailDomain::create([
            'domain' => 'menuvire.com',
            'from_email' => 'noreply@menuvire.com',
            'from_name' => 'MenuVire',
            'mailer' => 'exim',
            'status' => 'active',
            'daily_limit' => 1000,
            'hourly_limit' => 100,
        ]);

        $this->command->info("Domain created: {$domain->domain}");
        $this->command->info("API Key: {$domain->api_key}");
        $this->command->newLine();

        // Create OTP template
        EmailTemplate::create([
            'domain_id' => $domain->id,
            'template_key' => 'otp',
            'subject' => 'Your OTP Code - {{ $minutes }} minutes validity',
            'blade_html' => $this->getOtpTemplate(),
            'status' => 'active',
        ]);

        $this->command->info("Created template: otp");

        // Create Welcome template
        EmailTemplate::create([
            'domain_id' => $domain->id,
            'template_key' => 'welcome',
            'subject' => 'Welcome to {{ $appName }}!',
            'blade_html' => $this->getWelcomeTemplate(),
            'status' => 'active',
        ]);

        $this->command->info("Created template: welcome");

        // Create Invoice template
        EmailTemplate::create([
            'domain_id' => $domain->id,
            'template_key' => 'invoice',
            'subject' => 'Invoice #{{ $invoiceNumber }} - {{ $amount }}',
            'blade_html' => $this->getInvoiceTemplate(),
            'status' => 'active',
        ]);

        $this->command->info("Created template: invoice");
        $this->command->newLine();
        $this->command->info("✅ Email API seeding completed!");
    }

    private function getOtpTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; }
        .content { padding: 40px 30px; }
        .otp-box { background: #f8f9fa; padding: 25px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; margin: 20px 0; border-radius: 6px; border: 2px dashed #667eea; color: #667eea; }
        .warning { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h1 style="margin: 0;">Security Code</h1></div>
        <div class="content">
            <h2>Hello {{ $name }}!</h2>
            <p>You requested a one-time password (OTP) to verify your identity.</p>
            <div class="otp-box">{{ $otp }}</div>
            <p>This code will <span class="warning">expire in {{ $minutes }} minutes</span>.</p>
            <p>If you didn\'t request this code, please ignore this email.</p>
        </div>
    </div>
</body>
</html>';
    }

    private function getWelcomeTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 40px 30px; text-align: center; color: white; }
        .content { padding: 40px 30px; }
        .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h1 style="margin: 0;">Welcome to {{ $appName }}!</h1></div>
        <div class="content">
            <h2>Hi {{ $name }},</h2>
            <p>We\'re thrilled to have you on board! Your account has been successfully created.</p>
            <p style="text-align: center;"><a href="{{ $loginUrl }}" class="button">Get Started Now</a></p>
            <p>If you have any questions, our support team is here to help!</p>
        </div>
    </div>
</body>
</html>';
    }

    private function getInvoiceTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 40px auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%); padding: 30px; color: white; }
        .content { padding: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #ddd; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .total-row { font-weight: bold; font-size: 18px; background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">INVOICE</h1>
            <p style="margin: 5px 0 0 0;">Invoice #{{ $invoiceNumber }}</p>
        </div>
        <div class="content">
            <p><strong>Bill To:</strong> {{ $name }}</p>
            <p><strong>Due Date:</strong> {{ $dueDate }}</p>
            <table>
                <thead>
                    <tr><th>Description</th><th>Qty</th><th>Price</th><th>Total</th></tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td>{{ $item["description"] }}</td>
                        <td>{{ $item["quantity"] }}</td>
                        <td>${{ number_format($item["price"], 2) }}</td>
                        <td>${{ number_format($item["quantity"] * $item["price"], 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Total:</td>
                        <td>{{ $amount }}</td>
                    </tr>
                </tbody>
            </table>
            <p>Thank you for your business!</p>
        </div>
    </div>
</body>
</html>';
    }
}
