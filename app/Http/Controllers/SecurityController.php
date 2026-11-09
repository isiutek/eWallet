<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\PinService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SecurityController extends Controller
{
    public function index(Request $request, TwoFactorService $tfa)
    {
        $user = $request->user();
        $pendingSecret = $request->session()->get('2fa_setup_secret');

        return view('security.index', [
            'user' => $user,
            'setupSecret' => $pendingSecret,
            'setupQr' => $pendingSecret ? $tfa->qrSvg($user, $pendingSecret) : null,
            'logs' => AuditLog::where('user_id', $user->id)->latest('id')->limit(15)->get(),
        ]);
    }

    public function setPin(Request $request, PinService $pins)
    {
        $user = $request->user();
        $request->validate([
            'password' => 'required|current_password',
            'pin' => 'required|digits:6|confirmed',
        ]);
        $pins->set($user, $request->input('pin'));

        return back()->with('status', 'PIN transaksi tersimpan.');
    }

    public function startTwoFactor(Request $request, TwoFactorService $tfa)
    {
        $request->session()->put('2fa_setup_secret', $tfa->generateSecret());

        return back();
    }

    public function confirmTwoFactor(Request $request, TwoFactorService $tfa)
    {
        $request->validate(['code' => 'required|string']);
        $secret = $request->session()->get('2fa_setup_secret');
        if (! $secret || ! $tfa->verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Kode tidak cocok. Pastikan jam di HP sudah benar.']);
        }
        $request->user()->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget('2fa_setup_secret');
        $request->session()->put('2fa_passed', true);
        AuditLog::record('2fa.enabled');

        return back()->with('status', 'Autentikasi dua faktor aktif.');
    }

    public function disableTwoFactor(Request $request)
    {
        $request->validate(['password' => 'required|current_password']);
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::record('2fa.disabled');

        return back()->with('status', 'Autentikasi dua faktor dinonaktifkan.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->mixedCase()],
        ]);
        $request->user()->forceFill(['password' => Hash::make($request->input('password'))])->save();
        $request->user()->tokens()->delete(); // cabut semua token API
        AuditLog::record('password.changed');

        return back()->with('status', 'Password diperbarui. Semua sesi API dicabut.');
    }
}
