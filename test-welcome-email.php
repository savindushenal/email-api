<?php

/**
 * Test Script for Sending Welcome Emails
 * 
 * This script demonstrates how to send welcome emails using the Email API
 * with different domains and SMTP configurations.
 * 
 * Usage:
 * 1. Update the API_KEY with your domain's API key
 * 2. Update the recipient email
 * 3. Run: php test-welcome-email.php
 */

// API Configuration
$API_URL = 'http://localhost:8000/api';
$API_KEY = 'YOUR_API_KEY_HERE'; // Replace with actual API key from seeder

// Test Data
$testCases = [
    [
        'name' => 'Test 1: Welcome Email with Verification',
        'endpoint' => '/send-email',
        'data' => [
            'to' => 'user@example.com', // Change this to your email
            'template' => 'welcome',
            'data' => [
                'user_name' => 'John Doe',
                'platform_name' => 'MenuVire Platform',
                'verification_link' => 'https://app.menuvire.com/verify?token=abc123',
            ],
        ],
    ],
    [
        'name' => 'Test 2: Email Verification',
        'endpoint' => '/send-email',
        'data' => [
            'to' => 'user@example.com',
            'template' => 'verification',
            'data' => [
                'user_name' => 'Jane Smith',
                'verification_link' => 'https://app.menuvire.com/verify?token=xyz789',
                'verification_code' => '123456',
                'expiry_hours' => 24,
            ],
        ],
    ],
    [
        'name' => 'Test 3: Password Reset',
        'endpoint' => '/send-email',
        'data' => [
            'to' => 'user@example.com',
            'template' => 'password-reset',
            'data' => [
                'user_name' => 'Bob Johnson',
                'reset_link' => 'https://app.menuvire.com/reset-password?token=reset123',
                'expiry_hours' => 1,
            ],
        ],
    ],
];

echo "═══════════════════════════════════════════════════════════\n";
echo "  Email API - Welcome Email Test Suite\n";
echo "═══════════════════════════════════════════════════════════\n\n";

foreach ($testCases as $index => $test) {
    echo "► {$test['name']}\n";
    echo str_repeat("─", 60) . "\n";
    
    $ch = curl_init($API_URL . $test['endpoint']);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . $API_KEY,
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($test['data']),
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ CURL Error: {$error}\n\n";
        continue;
    }
    
    $result = json_decode($response, true);
    
    echo "Status Code: {$httpCode}\n";
    echo "Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    if ($httpCode === 200 && isset($result['success']) && $result['success']) {
        echo "✅ Email sent successfully!\n";
    } else {
        echo "❌ Failed to send email\n";
    }
    
    echo "\n";
    
    // Wait between requests
    if ($index < count($testCases) - 1) {
        sleep(2);
    }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "  Test Suite Completed\n";
echo "═══════════════════════════════════════════════════════════\n";
