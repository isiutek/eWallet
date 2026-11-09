<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Topup;
use App\Services\TopupService;
use Illuminate\Http\Request;

class TopupController extends Controller
{
    public function index(Request $request, TopupService $topups)
    {
        return view('topup.index', [
            'methods' => $topups->availableMethods(),
            'min' => (int) Setting::get('topup_min'),
            'max' => (int) Setting::get('topup_max'),
            'bankInfo' => Setting::get('manual_bank_info'),
            'history' => Topup::where('user_id', $request->user()->id)->latest('id')->paginate(10),
        ]);
    }

    public function store(Request $request, TopupService $topups)
    {
        $data = $request->validate([
            'method' => 'required|in:manual,xendit',
            'amount' => 'required|integer|min:1',
            'sender_name' => 'required_if:method,manual|nullable|string|max:100',
            'proof' => 'required_if:method,manual|nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        if ($data['method'] === 'xendit') {
            $topup = $topups->createXendit($request->user(), (int) $data['amount']);

            return redirect()->away($topup->xendit_invoice_url);
        }

        $topups->createManual($request->user(), (int) $data['amount'], $request->file('proof'), $data['sender_name'] ?? null);

        return redirect()->route('topup.index')->with('status', 'Permintaan isi saldo dikirim dan menunggu verifikasi admin.');
    }
}
