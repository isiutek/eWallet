@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@if (! auth()->user()->hasPin() || ! auth()->user()->hasTwoFactor())
    <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
        Lengkapi keamanan akun:
        @unless (auth()->user()->hasPin()) buat <b>PIN transaksi</b> (wajib untuk transaksi keluar)@endunless
        @unless (auth()->user()->hasTwoFactor()) @unless(auth()->user()->hasPin())dan @endunless aktifkan <b>2FA</b>@endunless.
        <a href="{{ route('security.index') }}" class="font-semibold underline">Atur sekarang</a>
    </div>
@endif
<div class="grid gap-4 md:grid-cols-3">
    <div class="md:col-span-1 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white p-6 shadow">
        <div class="text-sm opacity-80">Saldo</div>
        <div class="text-3xl font-bold mt-1">{{ rupiah($wallet->balance) }}</div>
        <div class="text-xs opacity-80 mt-2">No. dompet <span class="font-mono">{{ $wallet->number }}</span></div>
        <div class="grid grid-cols-4 gap-2 mt-5 text-center text-xs">
            <a href="{{ route('topup.index') }}" class="rounded-lg bg-white/15 hover:bg-white/25 py-2">Isi</a>
            <a href="{{ route('transfer.create') }}" class="rounded-lg bg-white/15 hover:bg-white/25 py-2">Kirim</a>
            <a href="{{ route('qr.scan') }}" class="rounded-lg bg-white/15 hover:bg-white/25 py-2">Bayar</a>
            <a href="{{ route('qr.receive') }}" class="rounded-lg bg-white/15 hover:bg-white/25 py-2">Terima</a>
        </div>
    </div>
    <div class="md:col-span-2 grid grid-cols-2 gap-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Masuk bulan ini</div><div class="text-xl font-bold text-emerald-600">{{ rupiah($month['in']) }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Keluar bulan ini</div><div class="text-xl font-bold text-rose-600">{{ rupiah($month['out']) }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Keluar hari ini</div><div class="text-xl font-bold">{{ rupiah($today_out) }}</div><div class="text-xs text-slate-400">Batas harian {{ rupiah(\App\Models\Setting::get('daily_debit_limit')) }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Isi saldo menunggu</div><div class="text-xl font-bold">{{ $pending_topups }}</div><div class="text-xs text-slate-400">{{ $month['count'] }} transaksi bulan ini</div></div>
    </div>
</div>
<div class="grid gap-4 md:grid-cols-5 mt-4">
    <div class="md:col-span-3 rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="font-semibold mb-3">Arus kas 14 hari terakhir</h3>
        <canvas id="chart" height="140"></canvas>
    </div>
    <div class="md:col-span-2 rounded-2xl bg-white p-5 shadow-sm">
        <div class="flex justify-between mb-2"><h3 class="font-semibold">Transaksi terbaru</h3><a href="{{ route('transactions.index') }}" class="text-sm text-indigo-600">Semua</a></div>
        <ul class="divide-y text-sm">
            @forelse ($recent as $t)
                <li class="py-2 flex justify-between gap-2">
                    <a href="{{ route('transactions.show', $t) }}" class="truncate">{{ $t->description }}<div class="text-xs text-slate-400">{{ $t->created_at->format('d/m H:i') }}</div></a>
                    <span class="font-semibold whitespace-nowrap {{ $t->isCredit() ? 'text-emerald-600' : 'text-rose-600' }}">{{ $t->isCredit() ? '+' : '−' }}{{ rupiah($t->amount) }}</span>
                </li>
            @empty
                <li class="py-6 text-center text-slate-400">Belum ada transaksi.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
@push('scripts')
<script type="module">
const d = @json($chart);
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: { labels: d.labels, datasets: [
        { label: 'Masuk', data: d.in, backgroundColor: '#10b981' },
        { label: 'Keluar', data: d.out, backgroundColor: '#f43f5e' },
    ]},
    options: { scales: { y: { ticks: { callback: v => 'Rp ' + v.toLocaleString('id-ID') } } },
               plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': Rp ' + c.raw.toLocaleString('id-ID') } } } }
});
</script>
@endpush
