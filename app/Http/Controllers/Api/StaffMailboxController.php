<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailDomain;
use App\Models\StaffMailbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffMailboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StaffMailbox::query()->with('domain:id,domain');

        if ($request->filled('domain')) {
            $domain = EmailDomain::where('domain', $request->input('domain'))->first();
            if (!$domain) {
                return response()->json(['success' => false, 'message' => 'Domain not found'], 404);
            }
            $query->where('email_domain_id', $domain->id);
        }

        if ($request->filled('staff_user_id')) {
            $query->where('staff_user_id', $request->input('staff_user_id'));
        }

        $mailboxes = $query->orderBy('email')->get()->map(fn (StaffMailbox $mailbox) => $this->serializeMailbox($mailbox));

        return response()->json([
            'success' => true,
            'data' => [
                'mailboxes' => $mailboxes,
                'count' => $mailboxes->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string',
            'email' => 'required|email|unique:staff_mailboxes,email',
            'display_name' => 'sometimes|string|max:255',
            'staff_user_id' => 'sometimes|string|max:36',
            'mailbox_password' => 'required|string|min:4',
            'smtp_host' => 'sometimes|string',
            'smtp_port' => 'sometimes|integer',
            'smtp_encryption' => 'sometimes|in:ssl,tls,null',
            'imap_host' => 'sometimes|string',
            'imap_port' => 'sometimes|integer',
            'imap_encryption' => 'sometimes|in:ssl,tls,null',
            'imap_folder' => 'sometimes|string|max:100',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $domain = EmailDomain::where('domain', $request->input('domain'))->first();
        if (!$domain) {
            return response()->json(['success' => false, 'message' => 'Domain not found'], 404);
        }

        $this->assertEmailOnDomain($request->input('email'), $domain->domain);

        $domainConfig = $domain->mail_config ?? [];
        $password = $request->input('mailbox_password');

        $mailbox = StaffMailbox::create([
            'email_domain_id' => $domain->id,
            'email' => strtolower($request->input('email')),
            'display_name' => $request->input('display_name'),
            'staff_user_id' => $request->input('staff_user_id'),
            'smtp_host' => $request->input('smtp_host', $domainConfig['host'] ?? null),
            'smtp_port' => $request->input('smtp_port', $domainConfig['port'] ?? 465),
            'smtp_encryption' => $request->input('smtp_encryption', $domainConfig['encryption'] ?? 'ssl'),
            'smtp_username' => strtolower($request->input('email')),
            'smtp_password' => $password,
            'imap_host' => $request->input('imap_host', $domainConfig['inbound']['host'] ?? $domainConfig['host'] ?? null),
            'imap_port' => $request->input('imap_port', $domainConfig['inbound']['port'] ?? 993),
            'imap_encryption' => $request->input('imap_encryption', $domainConfig['inbound']['encryption'] ?? 'ssl'),
            'imap_username' => strtolower($request->input('email')),
            'imap_password' => $password,
            'imap_folder' => $request->input('imap_folder', $domainConfig['inbound']['folder'] ?? 'INBOX'),
            'active' => $request->boolean('active', true),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->serializeMailbox($mailbox->fresh('domain')),
        ], 201);
    }

    public function update(Request $request, string $email): JsonResponse
    {
        $mailbox = StaffMailbox::where('email', strtolower($email))->first();
        if (!$mailbox) {
            return response()->json(['success' => false, 'message' => 'Mailbox not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'display_name' => 'sometimes|string|max:255',
            'staff_user_id' => 'sometimes|nullable|string|max:36',
            'mailbox_password' => 'sometimes|string|min:4',
            'smtp_host' => 'sometimes|string',
            'smtp_port' => 'sometimes|integer',
            'smtp_encryption' => 'sometimes|in:ssl,tls,null',
            'imap_host' => 'sometimes|string',
            'imap_port' => 'sometimes|integer',
            'imap_encryption' => 'sometimes|in:ssl,tls,null',
            'imap_folder' => 'sometimes|string|max:100',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updates = $request->only([
            'display_name',
            'staff_user_id',
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'imap_host',
            'imap_port',
            'imap_encryption',
            'imap_folder',
            'active',
        ]);

        if ($request->filled('mailbox_password')) {
            $password = $request->input('mailbox_password');
            $updates['smtp_password'] = $password;
            $updates['imap_password'] = $password;
        }

        $mailbox->update($updates);

        return response()->json([
            'success' => true,
            'data' => $this->serializeMailbox($mailbox->fresh('domain')),
        ]);
    }

    public function destroy(string $email): JsonResponse
    {
        $mailbox = StaffMailbox::where('email', strtolower($email))->first();
        if (!$mailbox) {
            return response()->json(['success' => false, 'message' => 'Mailbox not found'], 404);
        }

        $mailbox->delete();

        return response()->json(['success' => true, 'message' => 'Mailbox deleted']);
    }

    protected function serializeMailbox(StaffMailbox $mailbox): array
    {
        return [
            'id' => $mailbox->id,
            'email' => $mailbox->email,
            'display_name' => $mailbox->display_name,
            'staff_user_id' => $mailbox->staff_user_id,
            'domain' => $mailbox->domain?->domain,
            'active' => $mailbox->active,
            'last_polled_at' => $mailbox->last_polled_at?->toIso8601String(),
            'created_at' => $mailbox->created_at?->toIso8601String(),
        ];
    }

    protected function assertEmailOnDomain(string $email, string $domain): void
    {
        $parts = explode('@', strtolower($email));
        if (count($parts) !== 2 || $parts[1] !== strtolower($domain)) {
            abort(response()->json([
                'success' => false,
                'message' => "email must be an address on {$domain}",
            ], 422));
        }
    }
}
