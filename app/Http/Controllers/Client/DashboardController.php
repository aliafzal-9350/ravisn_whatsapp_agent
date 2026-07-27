<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CampaignRecipient;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $apiUsage = $this->getWhatsappApiUsage();

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
            'apiUsage' => $apiUsage,
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

    /**
     * Retrieve WhatsApp API usage and calculate US conversation pricing stats.
     *
     * @return array{
     *     categories: array{
     *         marketing: array{count: int, cost: string},
     *         authentication: array{count: int, cost: string},
     *         utility: array{count: int, cost: string},
     *         service: array{count: int, cost: string}
     *     },
     *     total_sent: int,
     *     total_cost: string
     * }
     */
    private function getWhatsappApiUsage(): array
    {
        $zeroedData = [
            'categories' => [
                'marketing' => ['count' => 0, 'cost' => '0.00'],
                'authentication' => ['count' => 0, 'cost' => '0.00'],
                'utility' => ['count' => 0, 'cost' => '0.00'],
                'service' => ['count' => 0, 'cost' => '0.00'],
            ],
            'total_sent' => 0,
            'total_cost' => '0.00',
        ];

        $wabaId = config('services.meta.waba_id');
        $accessToken = config('services.meta.access_token');

        if (empty($wabaId) || empty($accessToken)) {
            return $zeroedData;
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(5)
                ->get("https://graph.facebook.com/v20.0/{$wabaId}", [
                    'fields' => 'conversation_analytics.granularity(MONTHLY).dimensions(["conversation_category"])',
                ]);

            if ($response->failed()) {
                return $zeroedData;
            }

            $data = $response->json();
            $analytics = $data['conversation_analytics'] ?? [];

            $counts = [
                'marketing' => 0,
                'authentication' => 0,
                'utility' => 0,
                'service' => 0,
            ];

            $dataPoints = [];

            if (isset($analytics['data']) && is_array($analytics['data'])) {
                foreach ($analytics['data'] as $item) {
                    if (isset($item['data_points']) && is_array($item['data_points'])) {
                        foreach ($item['data_points'] as $dp) {
                            $dataPoints[] = $dp;
                        }
                    }
                }
            } elseif (isset($analytics['data_points']) && is_array($analytics['data_points'])) {
                $dataPoints = $analytics['data_points'];
            }

            foreach ($dataPoints as $dp) {
                $category = strtolower((string) ($dp['conversation_category'] ?? $dp['category'] ?? ''));
                $count = (int) ($dp['conversation_count'] ?? $dp['count'] ?? $dp['volume'] ?? 0);

                if (array_key_exists($category, $counts)) {
                    $counts[$category] += $count;
                }
            }

            $rates = [
                'marketing' => 0.025,
                'authentication' => 0.004,
                'utility' => 0.004,
                'service' => 0.00,
            ];

            $totalSent = 0;
            $totalCost = 0.0;
            $categories = [];

            foreach ($counts as $cat => $count) {
                $cost = $count * $rates[$cat];
                $totalSent += $count;
                $totalCost += $cost;

                $categories[$cat] = [
                    'count' => $count,
                    'cost' => number_format($cost, 2, '.', ''),
                ];
            }

            return [
                'categories' => $categories,
                'total_sent' => $totalSent,
                'total_cost' => number_format($totalCost, 2, '.', ''),
            ];
        } catch (\Throwable $e) {
            return $zeroedData;
        }
    }
}
