@extends('layouts.app')
@section('title', 'Terima QR')
@section('content')
<div class="grid gap-4 md:grid-cols-2">
    <div class="rounded-2xl bg-white p-6 shadow-sm text-center">
        <h2 class="text-lg font-semibold">QR saya (statis)</h2>
        <p class="text-sm text-slate-500 mb-3">Pembayar memasukkan nominal sendiri.</p>
        <div class="inline-block">{!! $staticSvg !!}</div>
        <div class="mt-2 text-sm">{{ auth()->user()->name }} · <span class="font-mono">{{ auth()->user()->wallet->number }}</span></div>
        <details class="mt-2 text-xs text-slate-400"><summary>Kode teks</summary><code class="break-all">{{ $staticPayload }}</code></details>
    </div>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">QR dengan nominal</h2>
        <p class="text-sm text-slate-500 mb-3">Sekali pakai, berlaku 15 menit.</p>
        @if ($dynamic)
            <div class="text-center">
                <div class="inline-block">{!! $dynamicSvg !!}</div>
                <div class="text-2xl font-bold mt-2">{{ rupiah($dynamic->amount) }}</div>
                <div class="text-sm text-slate-500">{{ $dynamic->description }}</div>
                <div id="qrstatus" class="mt-2 text-sm">
                    @if ($dynamic->paid_at)<span class="text-emerald-600 font-semibold">✔ Sudah dibayar</span>
                    @else<span class="text-amber-600">Menunggu pembayaran · berlaku s/d {{ $dynamic->expires_at->format('H:i') }}</span>@endif
                </div>
                <details class="mt-2 text-xs text-slate-400"><summary>Kode teks</summary><code class="break-all">{{ $dynamicPayload }}</code></details>
                <a href="{{ route('qr.receive') }}" class="inline-block mt-3 text-sm text-indigo-600">Buat QR baru</a>
            </div>
        @else
            <form method="POST" action="{{ route('qr.dynamic') }}" class="space-y-4">@csrf
                <label class="block text-sm">Nominal (Rp)<input name="amount" type="number" min="1000" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
                <label class="block text-sm">Keterangan<input name="description" maxlength="100" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
                <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5">Buat QR</button>
            </form>
        @endif
    </div>
</div>
@endsection
@if ($dynamic && ! $dynamic->paid_at)
@push('scripts')
<script>
const timer = setInterval(async () => {
    const r = await fetch('{{ route('qr.status', $dynamic->code) }}', { headers: { Accept: 'application/json' } });
    const j = await r.json();
    if (j.paid) { clearInterval(timer); document.getElementById('qrstatus').innerHTML = '<span class="text-emerald-600 font-semibold">✔ Sudah dibayar · ' + j.reference + '</span>'; }
}, 3000);
</script>
@endpush
@endif
