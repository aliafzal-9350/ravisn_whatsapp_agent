<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Services\WhatsApp\WhatsAppCloudApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 20, 60];
    public int $timeout = 30;

    public function __construct(
        public Campaign $campaign,
        public CampaignRecipient $recipient
    ) {
        $this->onQueue('campaigns');
    }

    public function handle(WhatsAppCloudApi $whatsAppApi): void
    {
        // 1. Skip if contact opted out
        $contact = Contact::where('tenant_id', $this->campaign->tenant_id)
            ->where('phone', $this->recipient->phone_number)
            ->first();

        if ($contact && ($contact->is_opted_out ?? false)) {
            $this->recipient->update([
                'status' => 'failed',
                'error_message' => 'Contact previously opted out (STOP).',
            ]);
            $this->campaign->increment('failed_count');
            return;
        }

        $account = $this->campaign->whatsappAccount;
        if (! $account) {
            $this->recipient->update([
                'status' => 'failed',
                'error_message' => 'WhatsApp account not configured.',
            ]);
            $this->campaign->increment('failed_count');
            return;
        }

        if ($account->access_token) {
            $whatsAppApi->withToken($account->access_token);
        }

        $template = $this->campaign->messageTemplate;
        if (! $template) {
            $this->recipient->update([
                'status' => 'failed',
                'error_message' => 'Template not found.',
            ]);
            $this->campaign->increment('failed_count');
            return;
        }

        try {
            $variables = $this->recipient->variables ?? [];
            $components = ! empty($variables) ? $template->getTemplateComponents($variables) : [];

            $response = $whatsAppApi->sendTemplateMessage(
                $account->phone_number_id,
                $this->recipient->phone_number,
                $template->name,
                $template->language,
                $components
            );

            if ($response->successful()) {
                $wamid = $response->json('messages.0.id');
                $this->recipient->update([
                    'status' => 'sent',
                    'whatsapp_message_id' => $wamid,
                    'sent_at' => now(),
                    'error_message' => null,
                ]);

                $this->campaign->increment('sent_count');
            } else {
                $status = $response->status();
                $errBody = $response->json('error.message', 'Unknown Meta Error');

                if ($status === 429 || $response->json('error.code') === 130429) {
                    $this->release(30);
                    return;
                }

                $this->recipient->update([
                    'status' => 'failed',
                    'error_message' => "Meta Error ({$status}): {$errBody}",
                ]);
                $this->campaign->increment('failed_count');
            }
        } catch (\Throwable $e) {
            Log::error('SendCampaignMessage job exception', [
                'recipient_id' => $this->recipient->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(10);
            } else {
                $this->recipient->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $this->campaign->increment('failed_count');
            }
        }
    }
}
