<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Models\WhatsappChat;
use App\Services\WhatsApp\WhatsAppCloudApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * Display the inbox.
     */
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        $accounts = $tenant->whatsappAccounts()
            ->where('status', 'active')
            ->get()
            ->map(fn ($acc) => [
                'id' => $acc->id,
                'phone_number' => $acc->phone_number,
                'display_name' => $acc->display_name,
            ]);

        $selectedAccountId = $request->query('whatsapp_account_id');
        if (! $selectedAccountId && $accounts->isNotEmpty()) {
            $selectedAccountId = $accounts->first()['id'];
        }

        $chats = [];
        if ($selectedAccountId) {
            $chats = WhatsappChat::where('tenant_id', $tenant->id)
                ->where('whatsapp_account_id', $selectedAccountId)
                ->orderBy('last_message_at', 'desc')
                ->get()
                ->map(function ($chat) use ($tenant) {
                    $contact = Contact::where('tenant_id', $tenant->id)
                        ->where('phone', $chat->customer_phone)
                        ->first();

                    // Calculate remaining session time (in seconds) based on the last inbound message
                    $lastInbound = $chat->messages()
                        ->where('direction', 'inbound')
                        ->latest('sent_at')
                        ->first();

                    $sessionRemaining = null;
                    if ($lastInbound) {
                        $sentAt = $lastInbound->sent_at ?? $lastInbound->created_at;
                        $diffSeconds = 86400 - now()->diffInSeconds($sentAt);
                        $sessionRemaining = $diffSeconds > 0 ? $diffSeconds : 0;
                    }

                    return [
                        'id' => $chat->id,
                        'customer_phone' => $chat->customer_phone,
                        'customer_name' => $contact ? $contact->name : $chat->customer_name,
                        'last_message_at' => $chat->last_message_at?->toIso8601String(),
                        'session_remaining' => $sessionRemaining,
                        'is_session_open' => $sessionRemaining > 0 && $sessionRemaining !== null,
                    ];
                });
        }

        $templates = $tenant->messageTemplates()
            ->where('whatsapp_account_id', $selectedAccountId)
            ->where('status', 'approved')
            ->get()
            ->map(fn ($template) => [
                'id' => $template->id,
                'name' => $template->name,
                'language' => $template->language,
                'category' => $template->category,
                'components' => $template->components,
            ]);

        return Inertia::render('client/inbox/index', [
            'accounts' => $accounts,
            'chats' => $chats,
            'selectedAccountId' => (int) $selectedAccountId,
            'templates' => $templates,
        ]);
    }

    /**
     * Get messages for a specific chat.
     */
    public function messages(Request $request, WhatsappChat $chat): JsonResponse
    {
        if ($chat->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $messages = $chat->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    /**
     * Send a text or template message to a customer.
     */
    public function sendMessage(Request $request, WhatsappChat $chat, WhatsAppCloudApi $whatsAppApi): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:text,template'],
            'body' => ['required_if:type,text', 'nullable', 'string'],
            'template_id' => ['required_if:type,template', 'nullable', 'integer'],
            'variables' => ['nullable', 'array'],
        ]);

        $type = $validated['type'] ?? 'text';

        if ($chat->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $account = $chat->whatsappAccount;
        if ($account->access_token) {
            $whatsAppApi->withToken($account->access_token);
        }

        // Validate 24-hour window on server side for free-text messages
        if ($type === 'text') {
            $lastInbound = $chat->messages()
                ->where('direction', 'inbound')
                ->latest('sent_at')
                ->first();

            $isSessionOpen = false;
            if ($lastInbound) {
                $sentAt = $lastInbound->sent_at ?? $lastInbound->created_at;
                $isSessionOpen = $sentAt->diffInHours(now()) < 24;
            }

            if (! $isSessionOpen) {
                return response()->json([
                    'error' => __('The 24-hour free-text session window is closed. Please send a template message instead.'),
                ], 403);
            }
        }

        try {
            if ($type === 'text') {
                $response = $whatsAppApi->sendTextMessage($account->phone_number_id, $chat->customer_phone, $validated['body']);
                $msgId = $response->json('messages.0.id');

                $message = $chat->messages()->create([
                    'direction' => 'outbound',
                    'message_type' => 'text',
                    'body' => $validated['body'],
                    'meta_message_id' => $msgId,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                $template = MessageTemplate::where('tenant_id', $request->user()->tenant_id)
                    ->where('id', $validated['template_id'])
                    ->firstOrFail();

                $components = [];
                if (! empty($validated['variables'])) {
                    $components = $template->getTemplateComponents($validated['variables']);
                }

                $response = $whatsAppApi->sendTemplateMessage(
                    $account->phone_number_id,
                    $chat->customer_phone,
                    $template->name,
                    $template->language,
                    $components
                );
                $msgId = $response->json('messages.0.id');

                $approxBody = $template->formatBodyText($validated['variables'] ?? []);


                $message = $chat->messages()->create([
                    'direction' => 'outbound',
                    'message_type' => 'template',
                    'body' => $approxBody,
                    'meta_message_id' => $msgId,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }

            $chat->update(['last_message_at' => now()]);

            return response()->json($message);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Inbox reply sending failed', [
                'chat_id' => $chat->id,
                'customer_phone' => $chat->customer_phone,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
