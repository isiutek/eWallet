<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Topup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TopupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['xendit.secret_key' => 'xnd_test', 'xendit.callback_token' => 'cb-token', 'xendit.verify_with_api' => true]);
    }

    public function test_manual_topup_needs_admin_approval(): void
    {
        Storage::fake('local');
        $user = $this->userWithBalance(0);
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)->post('/topup', [
            'method' => 'manual', 'amount' => 50000, 'sender_name' => 'Budi',
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect('/topup');

        $topup = Topup::first();
        $this->assertSame('pending', $topup->status);
        $this->assertSame(0, $user->wallet->fresh()->balance);

        $this->actingAs($user)->post("/admin/topups/{$topup->id}/approve")->assertForbidden();
        $this->actingAs($admin)->post("/admin/topups/{$topup->id}/approve")->assertRedirect();
        $this->actingAs($admin)->post("/admin/topups/{$topup->id}/approve"); // klik ganda

        $this->assertSame(50000, $user->wallet->fresh()->balance);
        $this->assertSame('paid', $topup->fresh()->status);
    }

    public function test_manual_topup_hidden_when_mode_is_xendit_only(): void
    {
        Setting::put('topup_mode', 'xendit');
        $user = $this->userWithBalance(0);

        $this->actingAs($user)->post('/topup', [
            'method' => 'manual', 'amount' => 50000, 'sender_name' => 'Budi',
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertSessionHasErrors('wallet');
        $this->assertSame(0, Topup::count());
    }

    private function createXenditTopup(User $user): Topup
    {
        Http::fake(['api.xendit.co/v2/invoices' => Http::response([
            'id' => 'inv_123', 'invoice_url' => 'https://checkout.xendit.co/web/inv_123', 'expiry_date' => now()->addDay()->toIso8601String(),
        ])]);
        $this->actingAs($user)->post('/topup', ['method' => 'xendit', 'amount' => 100000])
            ->assertRedirect('https://checkout.xendit.co/web/inv_123');

        return Topup::firstOrFail();
    }

    public function test_xendit_webhook_credits_once_and_requires_token(): void
    {
        $user = $this->userWithBalance(0);
        $topup = $this->createXenditTopup($user);
        Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response(['id' => 'inv_123', 'status' => 'PAID', 'amount' => 100000, 'paid_amount' => 100000, 'payment_channel' => 'BCA'])]);

        $payload = ['id' => 'inv_123', 'external_id' => $topup->reference, 'status' => 'PAID', 'paid_amount' => 100000];

        $this->postJson('/webhooks/xendit/invoice', $payload)->assertUnauthorized();
        $this->postJson('/webhooks/xendit/invoice', $payload, ['x-callback-token' => 'salah'])->assertUnauthorized();
        $this->assertSame(0, $user->wallet->fresh()->balance);

        $this->postJson('/webhooks/xendit/invoice', $payload, ['x-callback-token' => 'cb-token'])->assertOk();
        $this->postJson('/webhooks/xendit/invoice', $payload, ['x-callback-token' => 'cb-token'])->assertOk();

        $this->assertSame(100000, $user->wallet->fresh()->balance);
        $this->assertSame('paid', $topup->fresh()->status);
        $this->assertSame('BCA', $topup->fresh()->payment_channel);
    }

    public function test_forged_paid_webhook_is_ignored_when_xendit_says_pending(): void
    {
        $user = $this->userWithBalance(0);
        $topup = $this->createXenditTopup($user);
        Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response(['id' => 'inv_123', 'status' => 'PENDING', 'amount' => 100000])]);

        $this->postJson('/webhooks/xendit/invoice',
            ['id' => 'inv_123', 'external_id' => $topup->reference, 'status' => 'PAID', 'paid_amount' => 100000],
            ['x-callback-token' => 'cb-token'])->assertOk();

        $this->assertSame(0, $user->wallet->fresh()->balance);
        $this->assertSame('pending', $topup->fresh()->status);
    }
}
