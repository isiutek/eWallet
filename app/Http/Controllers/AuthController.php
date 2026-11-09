<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthService;
use App\Services\TwoFactorService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, AuthService $auth)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = $auth->attempt($data['email'], $data['password']);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('2fa_passed', ! $user->hasTwoFactor());
        AuditLog::record('login.success', $user->id);

        return $user->hasTwoFactor()
            ? redirect()->route('two-factor.challenge')
            : redirect()->intended(route('dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request, WalletService $wallets)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->mixedCase()],
        ]);
        $data['email'] = strtolower($data['email']);

        $user = DB::transaction(function () use ($data, $wallets) {
            $user = User::create($data);
            $wallets->createFor($user);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('2fa_passed', true);
        AuditLog::record('register', $user->id);

        return redirect()->route('security.index')->with('status', 'Akun dibuat. Silakan buat PIN transaksi dan aktifkan 2FA.');
    }

    public function showTwoFactor(Request $request)
    {
        if (! $request->user()->hasTwoFactor() || $request->session()->get('2fa_passed')) {
            return redirect()->route('dashboard');
        }

        return view('auth.two-factor');
    }

    public function verifyTwoFactor(Request $request, TwoFactorService $tfa)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user();

        if (! $tfa->verify($user->two_factor_secret, $request->input('code'))) {
            AuditLog::record('2fa.failed', $user->id);

            return back()->withErrors(['code' => 'Kode 2FA salah.']);
        }
        $request->session()->put('2fa_passed', true);
        AuditLog::record('2fa.passed', $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
