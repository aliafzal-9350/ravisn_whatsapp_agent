<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CampaignRecipient;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the client dashboard.
     */
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        $activeNumbers = $tenant->whatsappAccounts()->where('status', 'active')->count();
        $totalCampaigns = $tenant->campaigns()->count();

        // Count ALL outbound messages across all sources (inbox, API, campaigns, automation)
        $chatIds = $tenant->whatsappChats()->pluck('id');

        $totalMessagesSent = WhatsappMessage::whereIn('whatsapp_chat_id', $chatIds)
            ->where('direction', 'outbound')
            ->count();

        $totalDelivered = WhatsappMessage::whereIn('whatsapp_chat_id', $chatIds)
            ->where('direction', 'outbound')
            ->whereIn('status', ['delivered', 'read'])
            ->count();

        $totalFailed = WhatsappMessage::whereIn('whatsapp_chat_id', $chatIds)
            ->where('direction', 'outbound')
            ->where('status', 'failed')
            ->count();

        $totalReceived = WhatsappMessage::whereIn('whatsapp_chat_id', $chatIds)
            ->where('direction', 'inbound')
            ->count();

        $recentCampaigns = $tenant->campaigns()
            ->with('messageTemplate')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'message_label' => $campaign->message_type === 'direct'
                    ? __('Direct message')
                    : $campaign->messageTemplate?->name,
                'sent_count' => $campaign->sent_count,
                'delivered_count' => $campaign->delivered_count,
                'total_recipients' => $campaign->total_recipients,
                'progress' => $campaign->progressPercentage(),
                'created_at' => $campaign->created_at->diffForHumans(),
            ]);

        $weeklyActivity = $this->weeklyActivity($chatIds->all());
        $deliveryStatus = $this->deliveryStatus($tenant->campaigns()->pluck('id')->all());

        return Inertia::render('client/dashboard', [
            'stats' => [
                'activeNumbers' => $activeNumbers,
                'totalCampaigns' => $totalCampaigns,
                'totalMessagesSent' => $totalMessagesSent,
                'totalDelivered' => $totalDelivered,
                'totalFailed' => $totalFailed,
                'totalReceived' => $totalReceived,
            ],
            'recentCampaigns' => $recentCampaigns,
            'weeklyActivity' => $weeklyActivity,
            'deliveryStatus' => $deliveryStatus,
        ]);
    }

    /**
     * Build a real 7-day outbound/delivered trend from whatsapp_messages.
     *
     * @param  array<int>  $chatIds
     * @return array<int, array{day: string, date: string, sent: int, delivered: int}>
     */
    private function weeklyActivity(array $chatIds): array
    {
        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();

        $messages = empty($chatIds)
            ? collect()
            : WhatsappMessage::query()
                ->whereIn('whatsapp_chat_id', $chatIds)
                ->where('direction', 'outbound')
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('sent_at', [$start, $end])
                        ->orWhere(function ($query) use ($start, $end) {
                            $query->whereNull('sent_at')
                                ->whereBetween('created_at', [$start, $end]);
                        });
                })
                ->get(['status', 'sent_at', 'created_at']);

        $grouped = $messages->groupBy(function (WhatsappMessage $message): string {
            return ($message->sent_at ?? $message->created_at)->toDateString();
        });

        return collect(CarbonPeriod::create($start, '1 day', $end))
            ->map(function (Carbon $date) use ($grouped): array {
                $dayMessages = $grouped->get($date->toDateString(), collect());

                return [
                    'day' => $date->format('D'),
                    'date' => $date->toDateString(),
                    'sent' => $dayMessages->count(),
                    'delivered' => $dayMessages
                        ->whereIn('status', ['delivered', 'read'])
                        ->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build campaign recipient delivery breakdown from real recipient statuses.
     *
     * @param  array<int>  $campaignIds
     * @return array{total: int, delivered: int, failed: int, pending: int, sent: int, deliveryRate: int, unresolvedRate: int}
     */
    private function deliveryStatus(array $campaignIds): array
    {
        $counts = empty($campaignIds)
            ? collect()
            : CampaignRecipient::query()
                ->whereIn('campaign_id', $campaignIds)
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

        $delivered = (int) ($counts['delivered'] ?? 0) + (int) ($counts['read'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $sent = (int) ($counts['sent'] ?? 0);
        $pending = (int) ($counts['pending'] ?? 0);
        $total = $delivered + $failed + $sent + $pending;

        return [
            'total' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $pending,
            'sent' => $sent,
            'deliveryRate' => $total > 0 ? (int) round(($delivered / $total) * 100) : 0,
            'unresolvedRate' => $total > 0 ? (int) round((($failed + $pending + $sent) / $total) * 100) : 0,
        ];
    }
}
