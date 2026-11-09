<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\Topup;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AccountRecoveryService;
use App\Services\ReportService;
use App\Services\TopupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function dashboard()
    {
        $today = now()->startOfDay();

        return view('admin.dashboard', [
            'users' => User::count(),
            'total_balance' => (int) Wallet::sum('balance'),
            'pending_topups' => Topup::where('status', 'pending')->where('method', 'manual')->count(),
            'today_topup' => (int) Topup::where('status', 'paid')->where('paid_at', '>=', $today)->sum('amount'),
            'today_volume' => (int) Transaction::where('direction', 'debit')->where('created_at', '>=', $today)->sum('amount'),
            'today_count' => Transaction::where('created_at', '>=', $today)->count(),
            'recent_logs' => AuditLog::with('user')->latest('id')->limit(10)->get(),
        ]);
    }

    public function topups(Request $request)
    {
        $status = $request->query('status', 'pending');

        return view('admin.topups', [
            'status' => $status,
            'topups' => Topup::with('user', 'processor')->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function proof(Topup $topup)
    {
        abort_unless($topup->proof_path && Storage::disk('local')->exists($topup->proof_path), 404);

        return Storage::disk('local')->response($topup->proof_path);
    }

    public function approve(Request $request, Topup $topup, TopupService $topups)
    {
        $topups->approve($topup, $request->user(), $request->input('note'));

        return back()->with('status', "Isi saldo {$topup->reference} disetujui.");
    }

    public function reject(Request $request, Topup $topup, TopupService $topups)
    {
        $request->validate(['note' => 'required|string|max:200']);
        $topups->reject($topup, $request->user(), $request->input('note'));

        return back()->with('status', "Isi saldo {$topup->reference} ditolak.");
    }

    public function settings(TopupService $topups)
    {
        $values = [];
        foreach (array_keys(Setting::DEFAULTS) as $key) {
            $values[$key] = Setting::get($key);
        }

        return view('admin.settings', ['s' => $values, 'xenditConfigured' => ! empty(config('xendit.secret_key'))]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'topup_mode' => 'required|in:manual,xendit,both',
            'topup_min' => 'required|integer|min:1',
            'topup_max' => 'required|integer|gte:topup_min',
            'transfer_min' => 'required|integer|min:1',
            'daily_debit_limit' => 'required|integer|min:0',
            'manual_bank_info' => 'nullable|string|max:500',
        ]);
        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }
        AuditLog::record('admin.settings.updated', null, $data);

        return back()->with('status', 'Pengaturan disimpan.');
    }

    public function users(Request $request)
    {
        $q = $request->query('q');

        return view('admin.users', [
            'users' => User::with('wallet')->when($q, fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%"))
                ->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function toggleUser(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'Tidak bisa menonaktifkan akun sendiri.');
        $user->forceFill(['status' => $user->status === 'active' ? 'suspended' : 'active'])->save();
        if ($user->status === 'suspended') {
            $user->tokens()->delete();
        }
        AuditLog::record('admin.user.'.$user->status, null, ['target' => $user->id]);

        return back()->with('status', "Status {$user->email}: {$user->status}.");
    }

    public function resetPassword(Request $request, User $user, AccountRecoveryService $recovery)
    {
        abort_if($user->id === $request->user()->id, 422, 'Gunakan menu Keamanan untuk mengganti password sendiri.');
        $temp = $recovery->resetPassword($user, $request->user()->id);

        return back()->with('temp_password', ['email' => $user->email, 'name' => $user->name, 'password' => $temp]);
    }

    public function resetPin(Request $request, User $user, AccountRecoveryService $recovery)
    {
        $recovery->resetPin($user, $request->user()->id);

        return back()->with('status', "PIN {$user->email} direset. Pengguna perlu membuat PIN baru di menu Keamanan.");
    }

    public function resetTwoFactor(Request $request, User $user, AccountRecoveryService $recovery)
    {
        abort_if($user->id === $request->user()->id, 422, 'Gunakan menu Keamanan untuk mengatur 2FA sendiri.');
        $recovery->resetTwoFactor($user, $request->user()->id);

        return back()->with('status', "2FA {$user->email} dinonaktifkan. Pengguna bisa login tanpa kode lalu mengaktifkan ulang 2FA.");
    }

    public function transactions(Request $request, ReportService $reports)
    {
        $filters = array_filter($request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'type' => 'nullable|in:topup,transfer,qr_payment,adjustment', 'direction' => 'nullable|in:credit,debit',
            'q' => 'nullable|string|max:50',
        ]));
        $query = $reports->query(null, $filters);
        if ($request->query('export') === 'csv') {
            return $reports->csv($query);
        }

        return view('admin.transactions', [
            'filters' => $filters + ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString()],
            'summary' => $reports->summary($query),
            'transactions' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function auditLogs()
    {
        return view('admin.audit', ['logs' => AuditLog::with('user')->latest('id')->paginate(50)]);
    }
}
