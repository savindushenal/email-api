<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailDomain;
use App\Services\EmailService;
use App\Services\EmailSendOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailController extends Controller
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string',
            'template' => 'required|string',
            'to' => 'required|email',
            'data' => 'required|array',
            'from_email' => 'sometimes|email',
            'from_name' => 'sometimes|string|max:255',
            'reply_to' => 'sometimes|email',
            'in_reply_to' => 'sometimes|string|max:500',
            'references' => 'sometimes|string|max:2000',
            'cc' => 'sometimes|array|max:10',
            'cc.*' => 'email',
            'bcc' => 'sometimes|array|max:10',
            'bcc.*' => 'email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $authenticatedDomain = $request->get('authenticated_domain');

        if ($authenticatedDomain->domain !== $request->input('domain')) {
            return response()->json([
                'success' => false,
                'message' => 'Domain mismatch. Your API key is not authorized for this domain.',
                'authenticated_domain' => $authenticatedDomain->domain,
                'requested_domain' => $request->input('domain'),
            ], 403);
        }

        $options = EmailSendOptions::fromArray($request->all());
        $result = $this->emailService->send(
            $authenticatedDomain,
            $request->input('template'),
            $request->input('to'),
            $request->input('data'),
            $options
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function stats(Request $request): JsonResponse
    {
        $authenticatedDomain = $request->get('authenticated_domain');

        $period = $request->input('period', 'today');

        if (!in_array($period, ['today', 'week', 'month'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid period. Use: today, week, or month',
            ], 422);
        }

        $stats = $this->emailService->getStats($authenticatedDomain, $period);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Email API is running',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
