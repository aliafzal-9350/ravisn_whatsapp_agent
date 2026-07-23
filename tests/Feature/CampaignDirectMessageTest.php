<?php

use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppCloudApi;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Mockery\MockInterface;

it('creates a direct message campaign without a template', function () {
    $tenant = Tenant::create([
        'name' => 'Acme',
        'email' => 'billing@example.com',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'client',
    ]);

    $account = WhatsappAccount::create([
        'tenant_id' => $tenant->id,
        'waba_id' => '123456789',
        'app_id' => 'app-id',
        'app_secret' => 'app-secret',
        'phone_number_id' => 'phone-number-id',
        'phone_number' => '+15551234567',
        'display_name' => 'Sales',
        'status' => 'active',
        'quality_rating' => 'GREEN',
    ]);

    $this->actingAs($user)
        ->post(route('client.campaigns.store'), [
            'name' => 'Session follow up',
            'whatsapp_account_id' => $account->id,
            'message_type' => 'direct',
            'direct_message_body' => 'Thanks for chatting with us today.',
            'recipients' => [
                ['phone_number' => '+15557654321'],
            ],
        ])
        ->assertRedirect();

    $campaign = Campaign::query()->firstOrFail();

    expect($campaign)
        ->message_type->toBe('direct')
        ->message_template_id->toBeNull()
        ->direct_message_body->toBe('Thanks for chatting with us today.')
        ->total_recipients->toBe(1);

    $this->assertDatabaseHas('campaign_recipients', [
        'campaign_id' => $campaign->id,
        'phone_number' => '+15557654321',
    ]);
});

it('requires a body for direct message campaigns', function () {
    $tenant = Tenant::create([
        'name' => 'Acme',
        'email' => 'billing@example.com',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'client',
    ]);

    $account = WhatsappAccount::create([
        'tenant_id' => $tenant->id,
        'waba_id' => '123456789',
        'app_id' => 'app-id',
        'app_secret' => 'app-secret',
        'phone_number_id' => 'phone-number-id',
        'phone_number' => '+15551234567',
        'display_name' => 'Sales',
        'status' => 'active',
        'quality_rating' => 'GREEN',
    ]);

    $this->actingAs($user)
        ->from(route('client.campaigns.create'))
        ->post(route('client.campaigns.store'), [
            'name' => 'Empty direct message',
            'whatsapp_account_id' => $account->id,
            'message_type' => 'direct',
            'direct_message_body' => '',
            'recipients' => [
                ['phone_number' => '+15557654321'],
            ],
        ])
        ->assertRedirect(route('client.campaigns.create'))
        ->assertSessionHasErrors('direct_message_body');
});

it('sends direct campaigns as text messages', function () {
    $tenant = Tenant::create([
        'name' => 'Acme',
        'email' => 'billing@example.com',
        'status' => 'active',
    ]);

    $account = WhatsappAccount::create([
        'tenant_id' => $tenant->id,
        'waba_id' => '123456789',
        'app_id' => 'app-id',
        'app_secret' => 'app-secret',
        'phone_number_id' => 'phone-number-id',
        'phone_number' => '+15551234567',
        'display_name' => 'Sales',
        'status' => 'active',
        'quality_rating' => 'GREEN',
    ]);

    $campaign = Campaign::create([
        'tenant_id' => $tenant->id,
        'whatsapp_account_id' => $account->id,
        'message_type' => 'direct',
        'direct_message_body' => 'Thanks for chatting with us today.',
        'name' => 'Session follow up',
        'status' => 'processing',
        'total_recipients' => 1,
    ]);

    $recipient = CampaignRecipient::create([
        'campaign_id' => $campaign->id,
        'phone_number' => '+15557654321',
    ]);

    $whatsAppApi = $this->mock(WhatsAppCloudApi::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendTextMessage')
            ->once()
            ->with('phone-number-id', '+15557654321', 'Thanks for chatting with us today.')
            ->andReturn(new Response(new PsrResponse(200, [], '{"messages":[{"id":"wamid.123"}]}')));

        $mock->shouldNotReceive('sendTemplateMessage');
    });

    app(SendCampaignMessage::class, [
        'campaign' => $campaign,
        'recipient' => $recipient->refresh(),
    ])->handle($whatsAppApi);

    expect($recipient->refresh())
        ->status->toBe('sent')
        ->whatsapp_message_id->toBe('wamid.123');

    expect($campaign->refresh())
        ->status->toBe('completed')
        ->completed_at->not->toBeNull();
});
