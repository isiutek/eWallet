<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReportService
{
    /** Query transaksi terfilter. $walletId null = semua dompet (khusus admin). */
    public function query(?int $walletId, array $filters): Builder
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        return Transaction::query()
            ->with(['counterparty.user', 'wallet.user'])
            ->when($walletId, fn ($q) => $q->where('wallet_id', $walletId))
            ->whereBetween('created_at', [$from, $to])
            ->when(! empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['direction']), fn ($q) => $q->where('direction', $filters['direction']))
            ->when(! empty($filters['q']), fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', '%'.$filters['q'].'%')
                ->orWhere('description', 'like', '%'.$filters['q'].'%')))
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    public function summary(Builder $query): array
    {
        $rows = (clone $query)->reorder()->selectRaw('direction, COUNT(*) as cnt, COALESCE(SUM(amount),0) as total')
            ->groupBy('direction')->get()->keyBy('direction');
        $in = (int) ($rows['credit']->total ?? 0);
        $out = (int) ($rows['debit']->total ?? 0);

        return [
            'in' => $in,
            'out' => $out,
            'net' => $in - $out,
            'count' => (int) (($rows['credit']->cnt ?? 0) + ($rows['debit']->cnt ?? 0)),
        ];
    }

    /** Data grafik harian masuk/keluar untuk N hari terakhir. */
    public function daily(int $walletId, int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = Transaction::where('wallet_id', $walletId)->where('created_at', '>=', $start)
            ->get(['direction', 'amount', 'created_at']);

        $labels = $in = $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('d/m');
            $dayRows = $rows->filter(fn ($r) => $r->created_at->toDateString() === $key);
            $in[] = (int) $dayRows->where('direction', 'credit')->sum('amount');
            $out[] = (int) $dayRows->where('direction', 'debit')->sum('amount');
        }

        return compact('labels', 'in', 'out');
    }

    public function csv(Builder $query)
    {
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar rapi di Excel
            fputcsv($out, ['Tanggal', 'Referensi', 'Jenis', 'Arah', 'Nominal', 'Saldo Sebelum', 'Saldo Sesudah', 'Pihak Lain', 'Keterangan', 'Status']);
            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $t) {
                    fputcsv($out, [
                        $t->created_at->format('Y-m-d H:i:s'), $t->reference, $t->typeLabel(),
                        $t->isCredit() ? 'Masuk' : 'Keluar', $t->amount, $t->balance_before, $t->balance_after,
                        $t->counterparty?->user?->name, $t->description, $t->status,
                    ]);
                }
            });
            fclose($out);
        }, 'laporan-transaksi-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
