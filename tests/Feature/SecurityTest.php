<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_wallet(): void
    {
        $this->post('/register', [
            'name' => 'Andi', 'email' => 'andi@example.com',
            'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
        ])->assertRedirect('/security');

        $this->assertNotNull(User::where('email', 'andi@example.com')->first()->wallet);
    }

    public function test_account_locks_after_repeated_failed_logins(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::clear('login');
            $this->post('/login', ['email' => 'a@example.com', 'password' => 'salah']);
            $this->app['cache']->flush(); // pisahkan dari throttle per menit
        }
        $this->assertNotNull($user->fresh()->login_locked_until);

        $this->post('/login', ['email' => 'a@example.com', 'password' => 'Password123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_two_factor_is_required_after_password(): void
    {
        $g = new Google2FA();
        $secret = $g->generateSecretKey();
        $user = User::factory()->create(['email' => 'b@example.com']);
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $this->post('/login', ['email' => 'b@example.com', 'password' => 'Password123'])->assertRedirect('/two-factor');
        $this->get('/dashboard')->assertRedirect('/two-factor');

        $this->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/two-factor', ['code' => $g->getCurrentOtp($secret)])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_suspended_user_is_logged_out(): void
    {
        $user = $this->userWithBalance(0, ['status' => 'suspended']);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_open_admin(): void
    {
        $user = $this->userWithBalance(0);
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs($admin)->get('/admin/topups')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/transactions')->assertOk();
        $this->actingAs($admin)->get('/admin/audit')->assertOk();
    }

    public function test_pages_render(): void
    {
        $user = $this->userWithBalance(1000);
        foreach (['/dashboard', '/transfer', '/topup', '/qr/receive', '/qr/scan', '/transactions', '/security'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
}
