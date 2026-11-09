@extends('layouts.app')
@section('title', 'Bayar QR')
@section('content')
<div class="max-w-lg mx-auto rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold mb-3">Pindai QR untuk membayar</h2>
    <div id="reader" class="rounded-lg overflow-hidden bg-slate-900"></div>
    <p id="camerr" class="text-xs text-slate-500 mt-2"></p>
    <form method="GET" action="{{ route('qr.confirm') }}" id="f" class="space-y-3 mt-4">
        <label class="block text-sm">Atau tempel kode QR<input name="payload" id="payload" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
        <label class="block text-sm">Atau unggah gambar QR<input type="file" id="qrfile" accept="image/*" class="mt-1 block w-full text-sm"></label>
        <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5">Lanjut</button>
    </form>
</div>
@endsection
@push('scripts')
<script type="module">
const Html5Qrcode = await window.loadQrScanner();
const form = document.getElementById('f'), input = document.getElementById('payload');
function done(text) { input.value = text; form.submit(); }
const scanner = new Html5Qrcode('reader');
scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 230 }, t => { scanner.stop(); done(t); })
    .catch(() => { document.getElementById('camerr').textContent = 'Kamera tidak tersedia. Gunakan tempel kode atau unggah gambar.'; });
document.getElementById('qrfile').addEventListener('change', e => {
    const f = e.target.files[0]; if (!f) return;
    new Html5Qrcode('reader').scanFile(f, false).then(done).catch(() => alert('QR tidak terbaca dari gambar.'));
});
</script>
@endpush
