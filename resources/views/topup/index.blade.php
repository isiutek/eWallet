@extends('layouts.app')
@section('title', 'Isi Saldo')
@section('content')
@php $labels = ['pending' => ['Menunggu', 'bg-amber-100 text-amber-800'], 'paid' => ['Berhasil', 'bg-emerald-100 text-emerald-800'], 'rejected' => ['Ditolak', 'bg-rose-100 text-rose-800'], 'expired' => ['Kedaluwarsa', 'bg-slate-200 text-slate-600'], 'failed' => ['Gagal', 'bg-rose-100 text-rose-800']]; @endphp
@if (request('status') === 'success')
    <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">Pembayaran diterima. Saldo bertambah otomatis setelah konfirmasi dari Xendit (biasanya beberapa detik).</div>
@endif
<div class="grid gap-4 md:grid-cols-2">
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold mb-1">Isi saldo</h2>
        <p class="text-sm text-slate-500 mb-4">Nominal {{ rupiah($min) }} – {{ rupiah($max) }}</p>
        @if (empty($methods))
            <p class="text-sm text-rose-600">Isi saldo sedang tidak tersedia. Hubungi admin.</p>
        @else
        <form method="POST" action="{{ route('topup.store') }}" enctype="multipart/form-data" class="space-y-4">@csrf
            <label class="block text-sm">Nominal (Rp)<input name="amount" type="number" min="{{ $min }}" max="{{ $max }}" value="{{ old('amount') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
            <div class="flex gap-2 flex-wrap">
                @foreach ([100000, 250000, 500000, 1000000] as $q)
                    <button type="button" onclick="this.form.amount.value={{ $q }}" class="text-xs rounded-full border px-3 py-1 hover:bg-slate-50">{{ rupiah($q) }}</button>
                @endforeach
            </div>
            <fieldset class="space-y-2 text-sm">
                <legend class="mb-1">Metode</legend>
                @if (in_array('xendit', $methods))
                    <label class="flex gap-2 items-start rounded-lg border p-3"><input type="radio" name="method" value="xendit" {{ old('method', $methods[0]) === 'xendit' ? 'checked' : '' }} onchange="toggleManual()">
                        <span><b>Otomatis</b> (Virtual Account, QRIS, e-wallet, kartu via Xendit)<br><span class="text-xs text-slate-500">Saldo masuk otomatis setelah pembayaran.</span></span></label>
                @endif
                @if (in_array('manual', $methods))
                    <label class="flex gap-2 items-start rounded-lg border p-3"><input type="radio" name="method" value="manual" {{ old('method', $methods[0]) === 'manual' ? 'checked' : '' }} onchange="toggleManual()">
                        <span><b>Transfer manual</b><br><span class="text-xs text-slate-500">Upload bukti, saldo masuk setelah diverifikasi admin.</span></span></label>
                @endif
            </fieldset>
            <div id="manual" class="space-y-3 rounded-lg bg-slate-50 p-3">
                <div class="text-sm whitespace-pre-line"><b>Transfer ke:</b>
{{ $bankInfo }}</div>
                <label class="block text-sm">Nama pengirim<input name="sender_name" value="{{ old('sender_name') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
                <label class="block text-sm">Bukti transfer (JPG/PNG/PDF, maks 4MB)<input name="proof" type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full text-sm"></label>
            </div>
            <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5">Lanjutkan</button>
        </form>
        @endif
    </div>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h3 class="font-semibold mb-3">Riwayat isi saldo</h3>
        <ul class="divide-y text-sm">
            @forelse ($history as $t)
                <li class="py-2 flex justify-between items-center gap-2">
                    <div>{{ rupiah($t->amount) }} <span class="text-xs text-slate-400">· {{ $t->method === 'xendit' ? 'Otomatis' : 'Manual' }}</span>
                        <div class="text-xs text-slate-400">{{ $t->reference }} · {{ $t->created_at->format('d/m/Y H:i') }}</div>
                        @if ($t->admin_note)<div class="text-xs text-slate-500">Catatan: {{ $t->admin_note }}</div>@endif
                    </div>
                    <div class="text-right">
                        <span class="text-xs rounded-full px-2 py-0.5 {{ $labels[$t->status][1] ?? '' }}">{{ $labels[$t->status][0] ?? $t->status }}</span>
                        @if ($t->status === 'pending' && $t->xendit_invoice_url)
                            <a href="{{ $t->xendit_invoice_url }}" class="block text-xs text-indigo-600 mt-1">Bayar</a>
                        @endif
                    </div>
                </li>
            @empty
                <li class="py-6 text-center text-slate-400">Belum ada.</li>
            @endforelse
        </ul>
        {{ $history->links() }}
    </div>
</div>
@endsection
@push('scripts')
<script>
function toggleManual() {
    const m = document.querySelector('input[name=method]:checked');
    const box = document.getElementById('manual'); if (!box) return;
    box.style.display = m && m.value === 'manual' ? '' : 'none';
}
toggleManual();
</script>
@endpush
