<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TemplateController extends Controller
{
    /**
     * List all templates for a domain
     */
    public function index(Request $request): JsonResponse
    {
        $domain = $request->get('authenticated_domain');
        
        $query = EmailTemplate::where('domain_id', $domain->id);
        
        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        $templates = $query->select('id', 'template_key', 'category', 'description', 'subject', 'status', 'created_at', 'updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'domain' => $domain->domain,
                'templates' => $templates,
                'count' => $templates->count(),
            ],
        ]);
    }

    /**
     * Get a specific template
     */
    public function show(Request $request, string $templateKey): JsonResponse
    {
        $domain = $request->get('authenticated_domain');
        
        $template = EmailTemplate::where('domain_id', $domain->id)
            ->where('template_key', $templateKey)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => "Template '{$templateKey}' not found",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Create a new template
     */
    public function store(Request $request): JsonResponse
    {
        $domain = $request->get('authenticated_domain');

        $validator = Validator::make($request->all(), [
            'template_key' => 'required|string|max:100|regex:/^[a-z0-9_-]+$/',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'subject' => 'required|string|max:255',
            'blade_html' => 'required|string',
            'variables' => 'nullable|array',
            'variables.*.name' => 'required|string',
            'variables.*.type' => 'required|in:string,number,boolean,date,url,email',
            'variables.*.description' => 'nullable|string',
            'variables.*.required' => 'boolean',
            'variables.*.default' => 'nullable',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if template key already exists for this domain
        $exists = EmailTemplate::where('domain_id', $domain->id)
            ->where('template_key', $request->template_key)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "Template '{$request->template_key}' already exists for this domain",
            ], 409);
        }

        // Validate Blade syntax by trying to compile it with mock data
        try {
            $mockData = [];
            if ($request->has('variables')) {
                foreach ($request->variables as $var) {
                    $mockData[$var['name']] = match($var['type']) {
                        'string' => 'Sample Text',
                        'number' => 100,
                        'boolean' => true,
                        'date' => now()->format('Y-m-d'),
                        'url' => 'https://example.com',
                        'email' => 'test@example.com',
                        default => 'Sample'
                    };
                }
            }
            \Illuminate\Support\Facades\Blade::render($request->blade_html, $mockData);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Blade template syntax',
                'error' => $e->getMessage(),
            ], 422);
        }

        $template = EmailTemplate::create([
            'domain_id' => $domain->id,
            'template_key' => $request->template_key,
            'category' => $request->category,
            'description' => $request->description,
            'subject' => $request->subject,
            'blade_html' => $request->blade_html,
            'variables' => $request->variables,
            'status' => $request->status ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'data' => $template,
        ], 201);
    }

    /**
     * Update an existing template
     */
    public function update(Request $request, string $templateKey): JsonResponse
    {
        $domain = $request->get('authenticated_domain');

        $template = EmailTemplate::where('domain_id', $domain->id)
            ->where('template_key', $templateKey)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => "Template '{$templateKey}' not found",
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'category' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'subject' => 'sometimes|string|max:255',
            'blade_html' => 'sometimes|string',
            'variables' => 'nullable|array',
            'variables.*.name' => 'required|string',
            'variables.*.type' => 'required|in:string,number,boolean,date,url,email',
            'variables.*.description' => 'nullable|string',
            'variables.*.required' => 'boolean',
            'variables.*.default' => 'nullable',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate Blade syntax if blade_html is being updated
        if ($request->has('blade_html')) {
            try {
                $mockData = [];
                $vars = $request->has('variables') ? $request->variables : ($template->variables ?? []);
                foreach ($vars as $var) {
                    $mockData[$var['name']] = match($var['type']) {
                        'string' => 'Sample Text',
                        'number' => 100,
                        'boolean' => true,
                        'date' => now()->format('Y-m-d'),
                        'url' => 'https://example.com',
                        'email' => 'test@example.com',
                        default => 'Sample'
                    };
                }
                \Illuminate\Support\Facades\Blade::render($request->blade_html, $mockData);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Blade template syntax',
                    'error' => $e->getMessage(),
                ], 422);
            }
        }

        $template->update($request->only(['category', 'description', 'subject', 'blade_html', 'variables', 'status']));

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'data' => $template->fresh(),
        ]);
    }

    /**
     * Delete a template
     */
    public function destroy(Request $request, string $templateKey): JsonResponse
    {
        $domain = $request->get('authenticated_domain');

        $template = EmailTemplate::where('domain_id', $domain->id)
            ->where('template_key', $templateKey)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => "Template '{$templateKey}' not found",
            ], 404);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => "Template '{$templateKey}' deleted successfully",
        ]);
    }

    /**
     * Preview a template with sample data
     */
    public function preview(Request $request, string $templateKey): JsonResponse
    {
        $domain = $request->get('authenticated_domain');

        $template = EmailTemplate::where('domain_id', $domain->id)
            ->where('template_key', $templateKey)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => "Template '{$templateKey}' not found",
            ], 404);
        }

        $sampleData = $request->input('data', [
            'user_name' => 'John Doe',
            'platform_name' => $domain->from_name,
        ]);

        try {
            $renderedHtml = \Illuminate\Support\Facades\Blade::render($template->blade_html, $sampleData);
            $renderedSubject = \Illuminate\Support\Facades\Blade::render($template->subject, $sampleData);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Template rendering failed',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'template_key' => $templateKey,
                'subject' => $renderedSubject,
                'html' => $renderedHtml,
                'sample_data' => $sampleData,
            ],
        ]);
    }
}
