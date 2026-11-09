<?php

namespace App\Http\Controllers;

use App\Models\Topup;
use App\Models\Transaction;
use App\Services\ReportService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, WalletService $wallets, ReportService $reports)
    {
        $user = $request->user();
        $wallet = $wallets->createFor($user)->fresh();
        $monthQuery = $reports->query($wallet->id, ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]);

        return view('dashboard', [
            'wallet' => $wallet,
            'month' => $reports->summary($monthQuery),
            'today_out' => $wallets->todayDebitTotal($wallet),
            'chart' => $reports->daily($wallet->id, 14),
            'recent' => Transaction::with('counterparty.user')->where('wallet_id', $wallet->id)->latest('id')->limit(8)->get(),
            'pending_topups' => Topup::where('user_id', $user->id)->where('status', 'pending')->count(),
        ]);
    }
}
