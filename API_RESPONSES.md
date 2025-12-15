# API Response Examples

## Success Responses

### 1. Email Sent Successfully

**Request:**
```http
POST /api/email/send
X-API-Key: eak_abc123xyz789
Content-Type: application/json

{
  "domain": "menuvire.com",
  "template": "otp",
  "to": "user@example.com",
  "data": {
    "name": "John Doe",
    "otp": "492031",
    "minutes": 5
  }
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Email sent successfully",
  "data": {
    "log_id": 123,
    "message_id": "eak_abc123_xyz789",
    "to": "user@example.com",
    "from": "noreply@menuvire.com",
    "subject": "Your OTP Code - 5 minutes validity",
    "sent_at": "2023-12-15T10:30:00+00:00",
    "mailer": "exim"
  }
}
```

### 2. Statistics Retrieved

**Request:**
```http
GET /api/email/stats?period=today
X-API-Key: eak_abc123xyz789
```

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "total": 150,
    "sent": 145,
    "failed": 5,
    "queued": 0,
    "period": "today",
    "start_date": "2023-12-15T00:00:00+00:00"
  }
}
```

### 3. Health Check

**Request:**
```http
GET /api/health
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Email API is running",
  "version": "1.0.0",
  "timestamp": "2023-12-15T10:30:00+00:00"
}
```

## Error Responses

### 1. Missing API Key

**Request:**
```http
POST /api/email/send
Content-Type: application/json

{
  "domain": "menuvire.com",
  "template": "otp",
  "to": "user@example.com",
  "data": {}
}
```

**Response:** `401 Unauthorized`
```json
{
  "success": false,
  "message": "API key is required. Please provide X-API-Key header."
}
```

### 2. Invalid API Key

**Request:**
```http
POST /api/email/send
X-API-Key: invalid_key_here
Content-Type: application/json

{
  "domain": "menuvire.com",
  "template": "otp",
  "to": "user@example.com",
  "data": {}
}
```

**Response:** `401 Unauthorized`
```json
{
  "success": false,
  "message": "Invalid or inactive API key."
}
```

### 3. Domain Mismatch

**Request:**
```http
POST /api/email/send
X-API-Key: eak_valid_key_for_different_domain
Content-Type: application/json

{
  "domain": "unauthorized-domain.com",
  "template": "otp",
  "to": "user@example.com",
  "data": {}
}
```

**Response:** `403 Forbidden`
```json
{
  "success": false,
  "message": "Domain mismatch. Your API key is not authorized for this domain.",
  "authenticated_domain": "menuvire.com",
  "requested_domain": "unauthorized-domain.com"
}
```

### 4. Validation Errors

**Request:**
```http
POST /api/email/send
X-API-Key: eak_abc123xyz789
Content-Type: application/json

{
  "domain": "menuvire.com",
  "template": "otp",
  "to": "invalid-email",
  "data": {}
}
```

**Response:** `422 Unprocessable Entity`
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "to": [
      "The to field must be a valid email address."
    ]
  }
}
```

### 5. Missing Required Fields

**Request:**
```http
POST /api/email/send
X-API-Key: eak_abc123xyz789
Content-Type: application/json

{
  "domain": "menuvire.com"
}
```

**Response:** `422 Unprocessable Entity`
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "template": [
      "The template field is required."
    ],
    "to": [
      "The to field is required."
    ],
    "data": [
      "The data field is required."
    ]
  }
}
```

### 6. Template Not Found

**Request:**
```http
POST /api/email/send
X-API-Key: eak_abc123xyz789
Content-Type: application/json

{
  "domain": "menuvire.com",
  "template": "nonexistent_template",
  "to": "user@example.com",
  "data": {
    "name": "Test"
  }
}
```

**Response:** `500 Internal Server Error`
```json
{
  "success": false,
  "message": "Email sending failed",
  "error": "Template 'nonexistent_template' not found for domain 'menuvire.com'",
  "data": {
    "log_id": null
  }
}
```

### 7. Rate Limit Exceeded

**Request:**
```http
POST /api/email/send
X-API-Key: eak_abc123xyz789
Content-Type: application/json

{
  "domain": "menuvire.com",
  "template": "otp",
  "to": "user@example.com",
  "data": {
    "name": "Test"
  }
}
```

**Response:** `500 Internal Server Error`
```json
{
  "success": false,
  "message": "Email sending failed",
  "error": "Hourly rate limit exceeded",
  "data": {
    "log_id": null
  }
}
```

### 8. Too Many Requests

**Request:** (61st request in 1 minute)
```http
POST /api/email/send
X-API-Key: eak_abc123xyz789
```

**Response:** `429 Too Many Requests`
```json
{
  "message": "Too Many Attempts."
}
```

### 9. Template Rendering Error

**Request:**
```http
POST /api/email/send
X-API-Key: eak_abc123xyz789
Content-Type: application/json

{
  "domain": "menuvire.com",
  "template": "otp",
  "to": "user@example.com",
  "data": {}
}
```

**Response:** `500 Internal Server Error`
```json
{
  "success": false,
  "message": "Email sending failed",
  "error": "Template rendering failed: Undefined variable $name",
  "data": {
    "log_id": 124
  }
}
```

### 10. Domain Inactive

**Request:**
```http
POST /api/email/send
X-API-Key: eak_inactive_domain_key
Content-Type: application/json

{
  "domain": "inactive-domain.com",
  "template": "otp",
  "to": "user@example.com",
  "data": {}
}
```

**Response:** `401 Unauthorized`
```json
{
  "success": false,
  "message": "Invalid or inactive API key."
}
```

## HTTP Status Codes Reference

| Status Code | Meaning | When Used |
|------------|---------|-----------|
| 200 | OK | Request succeeded |
| 401 | Unauthorized | Missing or invalid API key |
| 403 | Forbidden | Domain authorization failed |
| 422 | Unprocessable Entity | Validation failed |
| 429 | Too Many Requests | Rate limit exceeded (throttle) |
| 500 | Internal Server Error | Email sending or processing failed |

## Response Structure

### Success Response Format
```json
{
  "success": true,
  "message": "Human-readable success message",
  "data": {
    // Response data
  }
}
```

### Error Response Format
```json
{
  "success": false,
  "message": "Human-readable error message",
  "error": "Detailed error description (optional)",
  "errors": {
    // Validation errors (422 only)
  },
  "data": {
    // Additional error context (optional)
  }
}
```

## Rate Limiting Headers

When rate limiting is applied, responses include:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
Retry-After: 30
```

## Best Practices

1. **Always Check `success` Field**: Don't rely solely on HTTP status codes
2. **Handle All Error Cases**: Implement proper error handling for all scenarios
3. **Log API Responses**: Keep logs for debugging and monitoring
4. **Implement Retry Logic**: For 429 and 500 errors with exponential backoff
5. **Validate Before Sending**: Validate data client-side before API calls
6. **Monitor Rate Limits**: Track usage to avoid hitting limits
7. **Store API Keys Securely**: Never expose in client-side code
8. **Use HTTPS**: Always use secure connections in production

## Example Client Implementations

### PHP
```php
function sendEmail($apiKey, $domain, $template, $to, $data) {
    $ch = curl_init('https://your-api.com/api/email/send');
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'domain' => $domain,
            'template' => $template,
            'to' => $to,
            'data' => $data,
        ]),
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 200 && $result['success']) {
        return $result['data'];
    }
    
    throw new Exception($result['message'] ?? 'Email sending failed');
}
```

### JavaScript (Node.js)
```javascript
async function sendEmail(apiKey, domain, template, to, data) {
    const response = await fetch('https://your-api.com/api/email/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-API-Key': apiKey,
        },
        body: JSON.stringify({
            domain,
            template,
            to,
            data,
        }),
    });
    
    const result = await response.json();
    
    if (response.ok && result.success) {
        return result.data;
    }
    
    throw new Error(result.message || 'Email sending failed');
}
```

### Python
```python
import requests
import json

def send_email(api_key, domain, template, to, data):
    response = requests.post(
        'https://your-api.com/api/email/send',
        headers={
            'Content-Type': 'application/json',
            'X-API-Key': api_key,
        },
        json={
            'domain': domain,
            'template': template,
            'to': to,
            'data': data,
        }
    )
    
    result = response.json()
    
    if response.status_code == 200 and result['success']:
        return result['data']
    
    raise Exception(result.get('message', 'Email sending failed'))
```
