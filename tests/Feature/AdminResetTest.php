<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_resets_password_and_user_must_change_it(): void
    {
        $admin = User::factory()->admin()->create();
        $user = $this->userWithBalance(0, ['email' => 'lupa@example.com']);
        $user->forceFill(['login_locked_until' => now()->addMinutes(10)])->save();
        $user->createToken('hp');

        $this->actingAs($user)->post("/admin/users/{$user->id}/reset-password")->assertForbidden();

        $res = $this->actingAs($admin)->post("/admin/users/{$user->id}/reset-password");
        $res->assertRedirect()->assertSessionHas('temp_password');
        $temp = session('temp_password')['password'];

        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->login_locked_until);
        $this->assertSame(0, $user->tokens()->count());

        $this->post('/logout');
        $this->post('/login', ['email' => 'lupa@example.com', 'password' => 'Password123'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'lupa@example.com', 'password' => $temp])->assertRedirect('/dashboard');

        // Sebelum ganti password, semua halaman diarahkan ke Keamanan
        $this->get('/dashboard')->assertRedirect('/security');
        $this->get('/transfer')->assertRedirect('/security');
        $this->get('/security')->assertOk();

        $this->post('/security/password', [
            'current_password' => $temp, 'password' => 'BaruSekali123', 'password_confirmation' => 'BaruSekali123',
        ])->assertRedirect('/security');
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/dashboard')->assertOk();
    }

    public function test_admin_resets_pin_and_two_factor(): void
    {
        $admin = User::factory()->admin()->create();
        $user = $this->userWithBalance(0);
        $user->forceFill(['two_factor_secret' => 'ABCDEFGHIJKLMNOP', 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($admin)->post("/admin/users/{$user->id}/reset-pin")->assertRedirect();
        $this->actingAs($admin)->post("/admin/users/{$user->id}/reset-2fa")->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->hasPin());
        $this->assertFalse($user->hasTwoFactor());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user.pin_reset']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user.2fa_reset']);
    }

    public function test_users_page_shows_reset_buttons(): void
    {
        $admin = User::factory()->admin()->create();
        $this->userWithBalance(0);
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Reset password')->assertSee('Reset PIN');
    }
}
