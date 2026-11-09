@extends('layouts.app')
@section('title', 'Transfer')
@section('content')
<div class="max-w-lg mx-auto rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold">Transfer saldo</h2>
    <p class="text-sm text-slate-500 mb-4">Saldo Anda: <b>{{ rupiah($wallet->balance) }}</b></p>
    <form method="POST" action="{{ route('transfer.store') }}" class="space-y-4" id="f">@csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotency }}">
        <label class="block text-sm">No. dompet / email / no. HP penerima
            <input name="to" id="to" value="{{ old('to', request('to')) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </label>
        <div id="recipient" class="text-sm -mt-2"></div>
        <label class="block text-sm">Nominal (Rp)<input name="amount" type="number" min="1" value="{{ old('amount') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
        <label class="block text-sm">Catatan (opsional)<input name="note" maxlength="100" value="{{ old('note') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
        @include('partials.pin')
        <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5">Kirim</button>
    </form>
</div>
@endsection
@push('scripts')
<script>
const to = document.getElementById('to'), box = document.getElementById('recipient');
async function lookup() {
    if (!to.value.trim()) { box.textContent = ''; return; }
    const r = await fetch('{{ route('transfer.lookup') }}?to=' + encodeURIComponent(to.value), { headers: { Accept: 'application/json' } });
    const j = await r.json();
    box.innerHTML = j.name ? '<span class="text-emerald-700">✔ ' + j.name.replace(/</g, '&lt;') + ' (' + j.number + ')</span>' : '<span class="text-rose-600">Penerima tidak ditemukan</span>';
}
to.addEventListener('change', lookup); if (to.value) lookup();
document.getElementById('f').addEventListener('submit', e => { e.submitter && (e.submitter.disabled = true); });
</script>
@endpush
