<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\ReportService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $filters = $this->filters($request);
        $query = $reports->query($request->user()->wallet->id, $filters);

        if ($request->query('export') === 'csv') {
            return $reports->csv($query);
        }

        return view('transactions.index', [
            'filters' => $filters + ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString()],
            'summary' => $reports->summary($query),
            'transactions' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function show(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->wallet_id === $request->user()->wallet->id, 404);

        return view('transactions.show', ['trx' => $transaction->load('counterparty.user')]);
    }

    private function filters(Request $request): array
    {
        return array_filter($request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'type' => 'nullable|in:topup,transfer,qr_payment,adjustment',
            'direction' => 'nullable|in:credit,debit',
            'q' => 'nullable|string|max:50',
        ]));
    }
}
