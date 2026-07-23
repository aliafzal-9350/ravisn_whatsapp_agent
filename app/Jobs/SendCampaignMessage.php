<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\SystemNotification;
use App\Services\WhatsApp\WhatsAppCloudApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Campaign $campaign,
        public CampaignRecipient $recipient,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsAppCloudApi $whatsAppApi): void
    {
        // Skip if campaign is paused or recipient already processed
        if ($this->campaign->status === 'paused' || $this->recipient->status !== 'pending') {
            return;
        }

        $tenant = $this->campaign->tenant;
        $account = $this->campaign->whatsappAccount;

        try {
            // Configure custom token if account has one (Embedded Signup)
            if ($account->access_token) {
                $whatsAppApi->withToken($account->access_token);
            }

            $response = $this->campaign->message_type === 'direct'
                ? $whatsAppApi->sendTextMessage(
                    $account->phone_number_id,
                    $this->recipient->phone_number,
                    $this->campaign->direct_message_body ?? '',
                )
                : $this->sendTemplateMessage($whatsAppApi, $account->phone_number_id);

            $messageId = $response->json('messages.0.id');

            $this->recipient->update([
                'status' => 'sent',
                'whatsapp_message_id' => $messageId,
                'sent_at' => now(),
            ]);

            // Update campaign counter
            $this->campaign->increment('sent_count');

            $this->checkCampaignCompletion();

        } catch (\Exception $e) {
            Log::error('Failed to send campaign message', [
                'campaign_id' => $this->campaign->id,
                'recipient_id' => $this->recipient->id,
                'error' => $e->getMessage(),
            ]);

            $this->recipient->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->campaign->increment('failed_count');

            // Check if campaign is completed after failure
            $this->checkCampaignCompletion();
        }
    }

    /**
     * Send a templated WhatsApp campaign message.
     */
    protected function sendTemplateMessage(WhatsAppCloudApi $whatsAppApi, string $phoneNumberId): Response
    {
        $template = $this->campaign->messageTemplate;
        $components = [];

        if ($this->recipient->template_variables) {
            $components = $template->getTemplateComponents($this->recipient->template_variables);
        }

        return $whatsAppApi->sendTemplateMessage(
            $phoneNumberId,
            $this->recipient->phone_number,
            $template->name,
            $template->language,
            $components,
        );
    }

    /**
     * Calculate the cost for this message based on country pricing.
     */
    protected function calculateCost(): float
    {
        // Default fallback cost
        return 0.05;
    }

    /**
     * Check if the campaign has finished processing all recipients.
     */
    protected function checkCampaignCompletion(): void
    {
        $campaign = $this->campaign->fresh();

        if (! $campaign || $campaign->status !== 'processing') {
            return;
        }

        if ($campaign->hasProcessedAllRecipients()) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Notify if failures occurred
            if ($campaign->failed_count > 0) {
                SystemNotification::create([
                    'tenant_id' => $campaign->tenant_id,
                    'title' => 'فشل جزئي أو كلي في إرسال الحملة',
                    'message' => "اكتملت الحملة \"{$campaign->name}\" مع فشل إرسال {$campaign->failed_count} رسالة من أصل {$campaign->total_recipients}.",
                    'type' => 'error',
                ]);
            }
        }
    }
}
